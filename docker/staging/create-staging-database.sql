SELECT 'CREATE DATABASE matches_api_staging'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'matches_api_staging')\gexec
