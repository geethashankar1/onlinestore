pipeline {
    agent any

    environment {
        REGISTRY      = 'ghcr.io'
        IMAGE_NAME    = 'geethashankar1/onlinestore'
        IMAGE_TAG     = "${REGISTRY}/${IMAGE_NAME}:${env.GIT_COMMIT?.take(7) ?: 'latest'}"
        IMAGE_LATEST  = "${REGISTRY}/${IMAGE_NAME}:latest"
        COMPOSE_PROD  = '/opt/eshop/docker-compose.prod.yml'
        COMPOSE_STAGE = '/opt/eshop/docker-compose.staging.yml'
    }

    stages {

        // ── 1. Lint (runs inside PHP Docker image — no PHP needed in Jenkins) ───
        stage('PHP Lint') {
            steps {
                sh '''
                    docker run --rm \
                        -v "$WORKSPACE:/app" \
                        -w /app \
                        php:8.3-cli \
                        sh -c '
                            ERROR=0
                            for f in $(find my_eshop -name "*.php"); do
                                php -l "$f" || ERROR=1
                            done
                            [ $ERROR -eq 0 ] && echo "All PHP files OK." || exit 1
                        '
                '''
            }
        }

        // ── 2. Build & push Docker image ──────────────────────────
        stage('Build & Push') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: 'ghcr-credentials',
                    usernameVariable: 'GHCR_USER',
                    passwordVariable: 'GHCR_PAT'
                )]) {
                    sh '''
                        echo "$GHCR_PAT" | docker login ghcr.io -u "$GHCR_USER" --password-stdin
                        docker build -t "$IMAGE_TAG" -t "$IMAGE_LATEST" .
                        docker push "$IMAGE_TAG"
                        docker push "$IMAGE_LATEST"
                        docker logout ghcr.io
                    '''
                }
            }
        }

        // ── 3a. Deploy staging (develop branch) ───────────────────
        stage('Deploy → Staging') {
            when { branch 'develop' }
            steps {
                sh '''
                    set -a && source /opt/eshop/.env && set +a
                    docker compose -f "$COMPOSE_STAGE" pull web
                    docker compose -f "$COMPOSE_STAGE" up -d --remove-orphans
                    docker image prune -f
                    echo "Staging deployed: http://64.227.187.60:8080"
                '''
            }
        }

        // ── 3b. Deploy production (main branch) ───────────────────
        stage('Deploy → Production') {
            when { branch 'main' }
            steps {
                sh '''
                    set -a && source /opt/eshop/.env && set +a
                    docker compose -f "$COMPOSE_PROD" pull web
                    docker compose -f "$COMPOSE_PROD" up -d --remove-orphans
                    docker image prune -f
                    echo "Production deployed: https://myeshopstore.online"
                '''
            }
        }
    }

    post {
        success {
            echo "Pipeline passed for branch: ${env.BRANCH_NAME}"
        }
        failure {
            echo "Pipeline FAILED for branch: ${env.BRANCH_NAME}"
        }
    }
}
