# Fly.io Volume Setup Commands

# 1. Create a volume for your database (adjust size as needed)
fly volumes create oposchat_data --region ord --size 1

# 2. List volumes to confirm creation
fly volumes list

# 3. Show volume details
fly volumes show oposchat_data