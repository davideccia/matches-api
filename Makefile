.PHONY: dev

dev:
	vendor/bin/sail up -d --wait
	npx concurrently -n "sail,queue,reverb" -c "red,green,blue" "vendor/bin/sail logs -f" "vendor/bin/sail artisan queue:work" "vendor/bin/sail artisan reverb:start"
