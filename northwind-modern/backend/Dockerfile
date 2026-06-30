# --- Stage 1: build ---
FROM gradle:8.10-jdk21 AS build
WORKDIR /app
# Cache dependencies first
COPY settings.gradle.kts build.gradle.kts ./
RUN gradle --no-daemon dependencies > /dev/null 2>&1 || true
COPY src ./src
RUN gradle --no-daemon clean bootJar -x test

# --- Stage 2: runtime ---
FROM eclipse-temurin:21-jre AS runtime
WORKDIR /app
# wget is used by the docker-compose healthcheck
RUN apt-get update && apt-get install -y --no-install-recommends wget && rm -rf /var/lib/apt/lists/*
COPY --from=build /app/build/libs/*.jar app.jar
EXPOSE 8080
ENTRYPOINT ["java", "-jar", "/app/app.jar"]
