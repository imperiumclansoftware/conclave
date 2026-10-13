#!/bin/sh
# Starts Conclave: checks the configuration, prepares the database and the keys, then runs the
# web server (default) or the given command (e.g. the Discord worker).
set -e

missing=''
for name in APP_SECRET DATABASE_URL MERCURE_JWT_SECRET JWT_PASSPHRASE CONCLAVE_URL; do
	eval "value=\${$name:-}"
	[ -n "$value" ] || missing="$missing $name"
done
if [ -n "$missing" ]; then
	echo "Conclave : variables d'environnement manquantes :$missing (voir docker/.env.example)." >&2
	exit 1
fi

CONCLAVE_URL="${CONCLAVE_URL%/}"
# Absolute http(s) address: the browser keeps the hub cookie over plain HTTP
export MERCURE_BROWSER_URL="${MERCURE_BROWSER_URL:-$CONCLAVE_URL/.well-known/mercure}"
export MERCURE_URL="${MERCURE_URL:-http://localhost/.well-known/mercure}"
# Claims of the hub tokens, checked by the hub (issuer, audience): same values on both sides
export MERCURE_ISSUER="${MERCURE_ISSUER:-$CONCLAVE_URL}"
export MERCURE_AUDIENCE="${MERCURE_AUDIENCE:-$MERCURE_BROWSER_URL}"
export DEFAULT_URI="${DEFAULT_URI:-$CONCLAVE_URL}"
export APP_PUBLIC_URL="${APP_PUBLIC_URL:-$CONCLAVE_URL/}"

mkdir -p /data/avatars /data/news-images /data/setup /data/jwt /data/caddy /data/caddy-config

echo 'Conclave : attente de la base de données…'
tries=0
until error=$(php bin/console dbal:run-sql -q 'SELECT 1' 2>&1); do
	tries=$((tries + 1))
	# The reason (password refused, host unknown…), without waiting for the end
	reason=$(printf '%s\n' "$error" | grep -Eo 'FATAL: .*|could not translate host name .*|Connection refused|timeout expired' | head -n 1 | cut -c1-200 | sed 's/""$/"/')
	if [ "$tries" -ge 60 ]; then
		echo "Conclave : base de données injoignable après 2 minutes : ${reason:-$error}" >&2
		echo 'Vérifiez POSTGRES_PASSWORD : il ne doit pas avoir changé depuis la création du volume de la base.' >&2
		exit 1
	fi
	if [ $((tries % 5)) -eq 1 ]; then
		echo "Conclave : base de données pas encore prête (${reason:-réponse inattendue}), nouvel essai…"
	fi
	sleep 2
done
echo 'Conclave : base de données prête.'

# The web container prepares the database and the keys; the worker only uses them
if [ "$1" = 'frankenphp' ]; then
	php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	php bin/console lexik:jwt:generate-keypair --skip-if-exists
	php bin/console app:setup:code
fi

exec "$@"
