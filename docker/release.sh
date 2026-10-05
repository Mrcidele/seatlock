#!/bin/sh
# Passo de release, executado uma vez por deploy ANTES de trocar as instâncias
# (ex.: job de pré-deploy no Kubernetes/ECS/Fly). As migrations precisam ser
# compatíveis com a versão anterior do código (expand/contract), pois as duas
# versões rodam juntas durante o rollout.
set -eu

php artisan migrate --force --isolated
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
