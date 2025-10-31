FROM maven:3.9-eclipse-temurin-11 AS build

WORKDIR /app

# Copy pom.xml and download dependencies
COPY pom.xml .
RUN mvn dependency:go-offline -B

# Copy source code
COPY src ./src

# Build application
RUN mvn clean package -DskipTests

# Runtime stage
FROM eclipse-temurin:11-jre

WORKDIR /app

# Copy built JAR
COPY --from=build /app/target/cbackup.jar app.jar

# Create non-root user
RUN groupadd -r appuser && useradd -r -g appuser appuser
RUN chown -R appuser:appuser /app

USER appuser

EXPOSE 8080

ENTRYPOINT ["java", "-jar", "app.jar"]

