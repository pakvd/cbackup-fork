FROM maven:3.9-eclipse-temurin-21 AS build

WORKDIR /app

# Copy pom.xml and download dependencies
COPY pom.xml .
RUN mvn dependency:go-offline -B

# Copy source code
COPY src ./src

# Build application
RUN mvn clean package -DskipTests

# Runtime stage
FROM eclipse-temurin:21-jre

WORKDIR /app

# Copy built JAR
COPY --from=build /app/target/cbackup.jar app.jar

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Create non-root user
RUN groupadd -r appuser && useradd -r -g appuser appuser
RUN chown -R appuser:appuser /app

# Create .ssh directory for SSH host keys (will be used by appuser)
RUN mkdir -p /app/.ssh && chown -R appuser:appuser /app/.ssh && chmod 700 /app/.ssh

# Note: USER is set to root temporarily in docker-compose.yml
# Entrypoint script will switch to appuser before starting Java
USER root

EXPOSE 8437

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

