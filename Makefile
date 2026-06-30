.PHONY: release

release: ## Bump versione, commit e tag git (uso: make release V=1.2.3 [PUSH=1])
	@test -n "$(V)" || { echo "Specifica la versione: make release V=1.2.3 [PUSH=1]"; exit 1; }
	@grep -q '"version"' composer.json || { echo "Campo 'version' non trovato in composer.json"; exit 1; }
	@node -e "const p=require('./composer.json'); p.version='$(V)'; require('fs').writeFileSync('./composer.json', JSON.stringify(p, null, 2)+'\n');"
	git add composer.json
	git commit -m "chore: release v$(V)"
	git tag -a "v$(V)" -m "v$(V)"
	@if [ "$(PUSH)" = "1" ]; then \
		git push && git push --tags; \
	else \
		echo "Release v$(V) creata. Esegui 'git push && git push --tags' per pubblicare."; \
	fi
