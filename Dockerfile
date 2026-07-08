FROM php:8.2-cli

# Set the working directory
WORKDIR /app

# Install basic system dependencies
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Copy the entire project into the container
COPY . .

# Ensure the runtime directory exists and has correct permissions
RUN mkdir -p runtime && chmod -R 777 runtime

# Expose port 8000 (which is the one JAH uses internally)
EXPOSE 8000

# Start the PHP built-in server pointing to the public directory
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public/"]
