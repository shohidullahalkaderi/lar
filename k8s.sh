#!/usr/bin/env bash

# 1. Kill stale background port-forwards
pkill -f "kubectl port-forward" || true
# docker image prune -a -f
# docker system prune -a --volumes -f
kubectl delete namespace laravel-stack

# 2. Check if the Kind cluster exists; create it if missing
CLUSTER_NAME="kind"
if ! kind get clusters 2>/dev/null | grep -q "^${CLUSTER_NAME}$"; then
    echo "Creating Kind cluster '${CLUSTER_NAME}'..."
    kind create cluster --name "${CLUSTER_NAME}"
else
    echo "Kind cluster '${CLUSTER_NAME}' already exists."
fi

# 3. Ensure kubectl points to the Kind cluster context
kubectl config use-context "kind-${CLUSTER_NAME}"

# 4. Create the namespace cleanly
echo "Ensuring namespace 'laravel-stack' exists..."
kubectl create namespace laravel-stack --dry-run=client -o yaml | kubectl apply -f -

# 5. Build local Docker image
echo "Building local Docker image..."
docker build -t laravel-app:dev .

# 6. Load the image into the Kind cluster
echo "Loading Docker image into Kind cluster..."
kind load docker-image laravel-app:dev --name "${CLUSTER_NAME}"

# 7. Apply Kubernetes manifests into the namespace
echo "Applying Kubernetes manifests..."
kubectl apply -f k8s.yaml -n laravel-stack

# 8. Wait for the Laravel deployment to be fully ready
echo "Waiting for app rollout to finish..."
kubectl rollout status deployment/app -n laravel-stack --timeout=180s

echo '
# 9. Start port-forwarding in the background
kubectl port-forward --address 0.0.0.0 svc/app 8090:8000 -n laravel-stack &
kubectl port-forward --address 0.0.0.0 svc/db 3309:3306 -n laravel-stack &
kubectl port-forward --address 0.0.0.0 svc/redis 6390:6379 -n laravel-stack &

# 10. Running Laravel migrations, seed, and tests
kubectl exec deployment/app -n laravel-stack -- php artisan migrate
kubectl exec deployment/app -n laravel-stack -- php artisan db:seed
kubectl exec deployment/app -n laravel-stack -- env DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --do-not-cache-result

# 11. Display current pod statuses
kubectl get pods -n laravel-stack

# 12. test via terminal
curl -I http://localhost:8090

# 13. Trivy Privilege Escalation scan on the current directory
trivy config --quiet --include-non-failures Dockerfile | grep -E "(DS-0002|DS-0006|DS-0027)"
trivy config --quiet --include-non-failures k8s.yaml | grep -E "(KSV-0001|KSV-0003|KSV-0005|KSV-0012)"
'
# 12. Display current pod statuses
kubectl get pods -n laravel-stack