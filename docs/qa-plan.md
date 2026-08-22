# X-Files — QA Plan (Pre-Release)

**Version:** 0.1.0
**Date:** 2026-08-22
**Target:** Nextcloud App Store submission

---

## 1. App Store Compliance Checklist

Referência: [Nextcloud 34 Developer Manual — App Store Rules](https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/publishing.html)

| Requisito | Status | Evidência |
|---|---|---|
| Licença AGPL-3.0-or-later | ✅ | `LICENSE` file + `info.xml` |
| Nome não contém "Nextcloud" | ✅ | "X-Files" |
| Usa apenas API pública (OCP) | ✅ | PHPStan level 5, nenhum `\OC\*` |
| Compatível com latest NC +1 | ✅ | `min-version="28" max-version="34"` |
| Segue design guidelines (NC Vue) | ✅ | Todos componentes são `@nextcloud/vue` |
| Clean up on uninstall | ⚠️ VERIFICAR | Migration cria tabelas; NC auto-remove? AppData cleanup? |
| Code signed (signature.json) | ❌ TODO | Precisa CSR → PR → certificado → assinar |
| info.xml completo | ⚠️ VERIFICAR | screenshot, bugtracker, repository |
| Sem node_modules/vendor no tarball | ❌ TODO | CI workflow precisa excluir |
| Sem tests/ no tarball | ❌ TODO | CI workflow precisa excluir |
| Sem src/ (JS source) no tarball | ❌ TODO | Apenas js/ (built) deve ir |

---

## 2. Testes Funcionais (Manual)

### 2.1 Primeiro uso (Setup)

| # | Cenário | Passos | Resultado esperado |
|---|---|---|---|
| F01 | Primeiro acesso | Acessar /apps/xfiles sem vault | Tela "Set up your vault" |
| F02 | Criar vault | Preencher senha + confirmação + submit | Recovery key exibida |
| F03 | Senha curta | Tentar criar com < 4 chars | Botão desabilitado |
| F04 | Senhas não conferem | Digitar senhas diferentes | Erro "Passwords do not match" |
| F05 | Download recovery key | Clicar "Download key" | Arquivo .txt baixado com chave formatada |
| F06 | Confirmar recovery key | Clicar "I have saved my recovery key" | Transição para vault unlocked |

### 2.2 Lock / Unlock

| # | Cenário | Passos | Resultado esperado |
|---|---|---|---|
| F07 | Tela locked | Recarregar /apps/xfiles após setup | "The truth is out there" + campo senha |
| F08 | Unlock correto | Digitar senha correta + Unlock | Transição para galeria |
| F09 | Unlock incorreto | Digitar senha errada | "Invalid password" |
| F10 | Brute force (6x) | 6 tentativas erradas em sequência | "Too many attempts. Please wait." ou delay progressivo |
| F11 | Lock manual | Clicar ícone Lock no header | Volta para tela locked |
| F12 | Lock por tab close | Unlocked → trocar para outra tab → voltar | Vault locked novamente |
| F13 | Recovery | Clicar ícone → inserir key + nova senha | "Password reset successfully" |
| F14 | Recovery com key errada | Inserir key incorreta | "Invalid recovery key" |

### 2.3 Imagens

| # | Cenário | Passos | Resultado esperado |
|---|---|---|---|
| F15 | Upload JPEG | Upload arquivo .jpg | Imagem aparece no grid com thumbnail |
| F16 | Upload PNG | Upload arquivo .png | Imagem aparece com thumbnail |
| F17 | Upload múltiplo | Selecionar 3+ imagens | Toast "X image(s) uploaded", todas no grid |
| F18 | Upload arquivo inválido | Tentar upload de .pdf ou .txt | Erro "File type not allowed" |
| F19 | Upload muito grande | Upload > limite configurado | Erro "File exceeds maximum size" |
| F20 | Visualizar imagem | Clicar no thumbnail | Modal fullscreen com imagem |
| F21 | Download do viewer | Clicar "Download" no viewer | Arquivo baixado com nome original |
| F22 | Delete do grid | Hover → ícone delete → confirmar | Imagem removida do grid |
| F23 | Delete do viewer | Viewer → "Delete" → confirmar | Modal fecha, imagem removida |
| F24 | Empty state | Deletar todas as imagens | "Your vault is empty" com CTA upload |

### 2.4 Settings

| # | Cenário | Passos | Resultado esperado |
|---|---|---|---|
| F25 | Abrir settings | Clicar ícone engrenagem | Modal com timeout + max size + change password |
| F26 | Alterar timeout | Mudar para "15 minutes" | Toast "Settings saved" |
| F27 | Alterar max file size | Mudar para 100MB | Toast "Settings saved" |
| F28 | Mudar senha | Current + new + confirm → submit | "Password changed. Vault will lock now." |
| F29 | Mudar senha (errada) | Current password incorreta | "Current password is incorrect" |

### 2.5 Segurança (Isolamento)

| # | Cenário | Passos | Resultado esperado |
|---|---|---|---|
| F30 | Não aparece em Files | Navegar /apps/files | Nenhuma imagem do vault visível |
| F31 | Não aparece em Photos | Abrir /apps/photos | Nenhuma imagem do vault |
| F32 | Não aparece em Memories | Abrir /apps/memories | Nenhuma imagem do vault |
| F33 | Não aparece no Search | Buscar nome de imagem do vault | Zero resultados |
| F34 | Não aparece no WebDAV | PROPFIND via desktop client | Não listada |
| F35 | Acesso bloqueado locked | Tentar URL direta do thumb com vault locked | 403 Forbidden |

---

## 3. Testes de Regressão (Automatizáveis)

### 3.1 API Tests (curl)

```bash
# Setup
POST /vault/setup {password} → 200 + recovery_key
POST /vault/setup (duplicate) → 409 VAULT_EXISTS

# Status
GET /vault/status (no vault) → 200 {status: "not_setup"}
GET /vault/status (locked) → 200 {status: "locked"}
GET /vault/status (unlocked) → 200 {status: "unlocked", remaining_seconds}

# Unlock
POST /vault/unlock {correct} → 200
POST /vault/unlock {wrong} → 403 + throttle

# Lock
POST /vault/lock → 200

# Images (unlocked)
GET /images → 200 {images: [], total: 0}
POST /images/upload (file) → 200 {image: {...}}
GET /images/{id}/thumb → 200 image/jpeg
GET /images/{id}/download → 200 image/*
DELETE /images/{id} → 200

# Images (locked)
GET /images → 403 VAULT_LOCKED
GET /images/{id}/thumb → 403
GET /images/{id}/download → 403

# Cross-user
GET /images/{other_user_id} (as scully) → 404

# Settings
POST /vault/settings {auto_lock_seconds, max_file_size_mb} → 200
POST /vault/password {current, new} → 200
POST /vault/password {wrong current} → 403

# Recovery
POST /vault/recover {key, new_password} → 200
POST /vault/recover {wrong key} → 403
```

### 3.2 PHPStan

```bash
cd /opt/xfiles/app && vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
# Expected: 0 errors
```

---

## 4. Testes de Compatibilidade

| NC Version | PHP Version | Status |
|---|---|---|
| 34 (current server) | 8.4 | ✅ Testado |
| 28-33 | 8.2-8.3 | ⚠️ Não testado (testar após release se possível) |

**Nota:** Usamos apenas APIs públicas estáveis (IAppData, ISession, OCSController, QBMapper) que existem desde NC 12-20. Risco de incompatibilidade é baixo.

---

## 5. Checklist de Release (Fase 8)

### 5.1 Pré-requisitos

| # | Item | Status |
|---|---|---|
| 1 | Repositório público no GitHub | ❌ TODO (push para github.com/luisfacarmo/xfiles) |
| 2 | CSR gerada | ❌ TODO |
| 3 | PR no app-certificate-requests | ❌ TODO |
| 4 | Certificado recebido (.crt) | ❌ AGUARDANDO |
| 5 | info.xml com screenshot URLs | ❌ TODO |
| 6 | CHANGELOG com versão datada | ⚠️ Precisa remover [Unreleased] → [0.1.0] - date |

### 5.2 Build & Package

```bash
# 1. Bump version (já está 0.1.0 ou ajustar)
# 2. Build frontend
cd /opt/xfiles/app && npm ci && npm run build

# 3. Package tarball (excluindo dev files)
tar -czf xfiles-0.1.0.tar.gz \
  --transform "s,^app/,xfiles/," \
  --exclude="app/node_modules" \
  --exclude="app/tests" \
  --exclude="app/src" \
  --exclude="app/vendor" \
  --exclude="app/composer.json" \
  --exclude="app/composer.lock" \
  --exclude="app/package.json" \
  --exclude="app/package-lock.json" \
  --exclude="app/webpack.config.js" \
  --exclude="app/phpstan.neon.dist" \
  app/

# 4. Sign
occ integrity:sign-app \
  --privateKey=/path/to/xfiles.key \
  --certificate=/path/to/xfiles.crt \
  --path=/path/to/xfiles
```

### 5.3 Validação do Tarball

| # | Verificação | Comando |
|---|---|---|
| 1 | Sem node_modules | `tar -tzf xfiles-0.1.0.tar.gz | grep node_modules` (vazio) |
| 2 | Sem vendor/ | `tar -tzf xfiles-0.1.0.tar.gz | grep vendor/` (vazio) |
| 3 | Sem src/ | `tar -tzf xfiles-0.1.0.tar.gz | grep "src/"` (vazio) |
| 4 | Sem tests/ | `tar -tzf xfiles-0.1.0.tar.gz | grep tests/` (vazio) |
| 5 | Tem js/ (built) | `tar -tzf xfiles-0.1.0.tar.gz | grep "js/"` (tem arquivos) |
| 6 | Tem appinfo/ | `tar -tzf xfiles-0.1.0.tar.gz | grep appinfo/` (info.xml + routes.php) |
| 7 | Tem lib/ | `tar -tzf xfiles-0.1.0.tar.gz | grep "lib/"` (todos os PHP) |
| 8 | Tem templates/ | `tar -tzf xfiles-0.1.0.tar.gz | grep templates/` (main.php) |
| 9 | Tem img/ | `tar -tzf xfiles-0.1.0.tar.gz | grep "img/"` (app.svg) |
| 10 | signature.json presente | `tar -tzf xfiles-0.1.0.tar.gz | grep signature.json` |

### 5.4 Instalação limpa

```bash
# Simular instalação do tarball num NC limpo (ou desinstalar + reinstalar)
occ app:disable xfiles
rm -rf /var/www/nextcloud/apps/xfiles
tar -xzf xfiles-0.1.0.tar.gz -C /var/www/nextcloud/apps/
occ app:enable xfiles
# Verificar: tabelas criadas, rota funciona, página carrega
```

---

## 6. Itens Conhecidos (não bloqueiam release)

| Item | Prioridade | Versão |
|---|---|---|
| Seletor múltiplo + batch actions | Should have | v0.2 |
| FileAction "Send to X-Files" no Files app | Should have | v0.2 |
| Integração Memories/Photos (action menu) | Could have | v0.3 |
| PIN unlock | Could have | v1.1 |
| WebAuthn/FIDO2 | Could have | v1.2 |
| Criptografia at-rest | Could have | v2.0 |
| i18n (translations) | Should have | v0.2 |

---

## 7. Sequência de Ações para Submissão

1. **Push para GitHub** (repo público)
2. **Gerar CSR**: `openssl req -nodes -newkey rsa:4096 -keyout xfiles.key -out xfiles.csr -subj "/CN=xfiles"`
3. **Abrir PR** no `nextcloud/app-certificate-requests` com link do repo
4. **Aguardar certificado** (pode levar dias/semanas)
5. Enquanto aguarda: preparar screenshots, finalizar CHANGELOG
6. **Receber .crt** → assinar app → gerar tarball final
7. **Testar instalação limpa** do tarball
8. **Publicar release** no GitHub (tag v0.1.0)
9. **Submeter ao App Store** (via `R0Wi/nextcloud-appstore-push-action` ou manual)

---

## 8. Critérios de GO / NO-GO

### GO (pode publicar)

- [ ] Todos os testes F01-F35 passam
- [ ] PHPStan 0 errors
- [ ] Tarball não contém dev files
- [ ] signature.json válida
- [ ] Instalação limpa funciona
- [ ] info.xml completo (screenshot, bugtracker, repository)
- [ ] CHANGELOG datado

### NO-GO (bloqueia publicação)

- Qualquer teste F30-F35 (segurança) falhar
- PHPStan com erros
- Tarball contém node_modules ou vendor
- Certificado não recebido
- Instalação limpa falha
