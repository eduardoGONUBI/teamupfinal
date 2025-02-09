import pandas as pd
import numpy as np

# ============================================================
# 1. Load CSV Data
# ============================================================
df_users = pd.read_csv('users.csv')  # columns: [user_id, fav_sport, location, latitude, longitude]
df_events = pd.read_csv('events.csv')  # columns: [event_id, sport_id, location, status, latitude, longitude]
df_participation = pd.read_csv('event_user_participation.csv')  # columns: [user_id, event_id]

# ============================================================
# 2. Vectorized Haversine Formula for Distance Calculation
# ============================================================
def haversine_vectorized(lat1, lon1, lat2, lon2):
    """Calculate distances between (lat1, lon1) and array of (lat2, lon2) in km."""
    R = 6371  # Earth radius in km
    lat1, lon1, lat2, lon2 = map(np.radians, [lat1, lon1, lat2.values, lon2.values])
    dlat = lat2 - lat1
    dlon = lon2 - lon1
    a = np.sin(dlat/2)**2 + np.cos(lat1) * np.cos(lat2) * np.sin(dlon/2)**2
    c = 2 * np.arctan2(np.sqrt(a), np.sqrt(1 - a))
    return R * c

# ============================================================
# 3. Helper: Get Eligible Locations
# ============================================================
def get_eligible_locations(target_user_id, df_users, df_participation, df_events):
    target_user = df_users[df_users['user_id'] == target_user_id].iloc[0]
    home_loc = target_user['location']
    eligible = {home_loc}
    # Get locations from past participations
    user_events = df_participation[df_participation['user_id'] == target_user_id]
    if not user_events.empty:
        event_locs = user_events.merge(df_events, on='event_id')['location'].unique()
        eligible.update(event_locs)
    return eligible

# ============================================================
# 4. Improved Collaborative Filtering with Normalization
# ============================================================
def collaborative_filtering(target_user_id, df_users, df_events, df_participation):
    target_user = df_users[df_users['user_id'] == target_user_id].iloc[0]
    sport = target_user['fav_sport']
    loc = target_user['location']
    
    # Find similar users (same sport and nearby locations)
    similar_users = df_users[
        (df_users['fav_sport'] == sport) & 
        (df_users['user_id'] != target_user_id)
    ]['user_id'].tolist()
    
    if not similar_users:
        return pd.DataFrame(columns=['event_id', 'cf_score'])
    
    # Get events from similar users
    sim_events = df_participation[df_participation['user_id'].isin(similar_users)]
    sim_events = sim_events.merge(df_events[df_events['status'] != 'concluded'], on='event_id')
    if sim_events.empty:
        return pd.DataFrame(columns=['event_id', 'cf_score'])
    
    # Calculate CF scores and normalize
    cf_scores = sim_events.groupby('event_id').size().reset_index(name='cf_score')
    max_score = cf_scores['cf_score'].max()
    cf_scores['cf_score'] = cf_scores['cf_score'] / max_score if max_score > 0 else 0
    return cf_scores

# ============================================================
# 5. Content-Based Filtering with Enhanced Sport Preference
# ============================================================
def content_based_filtering(target_user_id, df_users, df_events, df_participation):
    target_user = df_users[df_users['user_id'] == target_user_id].iloc[0]
    fav_sport = target_user['fav_sport']
    
    # Get user's past sports
    past_events = df_participation.merge(df_events[df_events['status'] == 'concluded'], on='event_id')
    past_sports = past_events[past_events['user_id'] == target_user_id]['sport_id'].unique()
    eligible_sports = {fav_sport}.union(past_sports)
    
    # Get eligible locations
    eligible_locations = get_eligible_locations(target_user_id, df_users, df_participation, df_events)
    
    # Filter events
    cbf_events = df_events[
        (df_events['sport_id'].isin(eligible_sports)) &
        (df_events['location'].isin(eligible_locations)) &
        (df_events['status'] != 'concluded')
    ].copy()
    
    # Assign scores: 1.0 for fav sport, 0.7 for past sports
    cbf_events['cbf_score'] = cbf_events['sport_id'].apply(
        lambda x: 1.0 if x == fav_sport else (0.7 if x in past_sports else 0)
    )
    return cbf_events[['event_id', 'cbf_score']]

# ============================================================
# 6. Hybrid Recommendation with Conflict Resolution
# ============================================================
def hybrid_recommendation(target_user_id, df_users, df_events, df_participation, alpha=0.5, beta=0.3):
    if alpha + beta > 1:
        raise ValueError("Sum of alpha and beta must be ≤ 1")
    
    target_user = df_users[df_users['user_id'] == target_user_id].iloc[0]
    user_lat, user_lon = target_user['latitude'], target_user['longitude']
    eligible_locations = get_eligible_locations(target_user_id, df_users, df_participation, df_events)
    
    # Get CF and CBF scores
    cf_df = collaborative_filtering(target_user_id, df_users, df_events, df_participation)
    cbf_df = content_based_filtering(target_user_id, df_users, df_events, df_participation)
    
    # Merge scores
    hybrid_df = pd.merge(cbf_df, cf_df, on='event_id', how='outer').fillna(0)
    
    # Merge event details and calculate distance
    event_details = df_events[df_events['status'] != 'concluded'][['event_id', 'sport_id', 'location', 'latitude', 'longitude']]
    hybrid_df = pd.merge(hybrid_df, event_details, on='event_id', how='inner')
    
    hybrid_df['distance_km'] = haversine_vectorized(user_lat, user_lon, hybrid_df['latitude'], hybrid_df['longitude'])
    
    # Normalize distance score
    max_distance = hybrid_df['distance_km'].max() if not hybrid_df.empty else 1
    hybrid_df['distance_score'] = 1 - (hybrid_df['distance_km'] / max_distance) if max_distance > 0 else 1.0
    
    # Boost score for eligible locations
    hybrid_df['location_boost'] = hybrid_df['location'].isin(eligible_locations).astype(float)
    hybrid_df['distance_score'] = np.clip(hybrid_df['distance_score'] + hybrid_df['location_boost'] * 0.2, 0, 1)
    
    # Calculate hybrid score
    hybrid_df['hybrid_score'] = (1 - alpha - beta) * hybrid_df['cf_score'] + alpha * hybrid_df['cbf_score'] + beta * hybrid_df['distance_score']
    
    # Exclude already participated events
    user_events = df_participation[df_participation['user_id'] == target_user_id]['event_id']
    hybrid_df = hybrid_df[~hybrid_df['event_id'].isin(user_events)]
    
    # Final sorting and selection
    hybrid_df.sort_values(['hybrid_score', 'distance_score'], ascending=[False, False], inplace=True)
    return hybrid_df.head(5)[['event_id', 'sport_id', 'location', 'hybrid_score']]

# ============================================================
# 7. Example Usage
# ============================================================
if __name__ == "__main__":
    recommendations = hybrid_recommendation(
        target_user_id=3,
        df_users=df_users,
        df_events=df_events,
        df_participation=df_participation,
        alpha=0.5,
        beta=0.3
    )
    print("Recommendations:\n", recommendations)