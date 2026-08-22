# X-Files Integration Research Report

**Date:** 2026-08-22
**Server:** Nextcloud 34.0.3, PHP 8.4.24
**Apps:** Photos 7.0.0, Memories 8.1.0
**X-Files:** commit 1bc5645

---

## 1. Estado Atual da Implementação

### Arquivos envolvidos

| Arquivo | Função |
|---|---|
| `src/init.js` | Registra FileAction `xfiles-send-to-vault` via `@nextcloud/files` 3.12.2 |
| `lib/Listener/LoadAdditionalScriptsListener.php` | Injeta `xfiles-init.js` via `LoadAdditionalScriptsEvent` |
| `lib/Controller/ImageController.php` | Endpoint `POST /api/v1/images/import` |
| `lib/Service/ImageService.php` | `importFromUserFiles()` usa `IRootFolder->getUserFolder()->get(path)` |
| `webpack.config.js` | Entry point separado para `init` |

### Problemas identificados na implementação atual

| # | Problema | Severidade | Detalhe |
|---|---|---|---|
| 1 | `execBatch` usa `this.exec` com binding incorreto | Média | `this` no callback aponta para o objeto action, mas `exec` não é bound — pode falhar em batch |
| 2 | `file.path` pode não ser populado em todos os contextos | Média | Alguns providers retornam `Node` sem path completo |
| 3 | `LoadAdditionalScriptsEvent` dispara APENAS no Files app | Crítica | Photos e Memories NÃO emitem este evento |
| 4 | Sem validação de path traversal além do getUserFolder | Baixa | getUserFolder já confina ao user home — seguro, mas verbose errors poderiam leakar paths |
| 5 | `import` método está no `ImageController` protegido pelo `VaultSessionMiddleware` | Design | Vault deve estar unlocked para importar — behavior correto mas pode surpreender o usuário |

---

## 2. Nextcloud Photos (7.0.0) — Investigação Completa

### Evidências coletadas do servidor

| Verificação | Resultado |
|---|---|
| Photos emite `LoadAdditionalScriptsEvent`? | **NÃO** — grep no source PHP não encontra nenhuma dispatch |
| Photos lê de `window._nc_fileactions`? | **NÃO** — grep no JS (photos-main.mjs) retorna 0 matches |
| Photos usa `getFileActions()`? | **NÃO** — não encontrado no JS compiled |
| Photos usa `registerFileAction()`? | **NÃO** — apenas presente em sourcemaps (bundled dependency, não usado) |
| Photos tem sua própria action system? | **SIM** — Vue components: `ActionFavorite`, `NcActions`, `NcActionButton` |
| Photos exibe menu de contexto com ações de terceiros? | **NÃO** — ações são hardcoded nos Vue templates |

### Resposta às perguntas

**Pergunta A:** A FileAction atual do X-Files aparece no Photos?
> **NÃO.** O script `xfiles-init.js` não é carregado no contexto do Photos porque `LoadAdditionalScriptsEvent` não é emitido pelo Photos.

**Pergunta B:** Por que não aparece?
> Photos é uma SPA Vue independente que NÃO usa a infraestrutura de FileActions do Files app. Tem seu próprio sistema de ações baseado em Vue components hardcoded.

**Pergunta C:** Mecanismo oficial para adicionar ação de terceiros ao Photos?
> **NÃO EXISTE** no NC 34. Photos não oferece plugin API, extension point, ou registro de ações de terceiros.

**Pergunta D:** Diferenças entre contextos do Photos:

| Contexto | FileAction funciona? | Motivo |
|---|---|---|
| Photos timeline | ❌ | Vue SPA sem integração com FileAction registry |
| Photos albums | ❌ | Mesmo — Vue components próprios |
| Photos favorites | ❌ | Mesmo |
| Photos shared | ❌ | Mesmo |

### Possibilidades de integração com Photos

| Nível | Método | Viabilidade |
|---|---|---|
| A — API oficial | Não existe | ❌ |
| B — API pública não documentada | Não encontrada | ❌ |
| C — Evento Nextcloud | `BeforeTemplateRenderedEvent` pode injetar script globalmente | ⚠️ Possível mas não resolve (Photos não lê FileActions) |
| D — JS injection | Injetar script que manipula o DOM/Vue do Photos para adicionar botão | ⚠️ Hack frágil |

---

## 3. Memories (8.1.0) — Investigação Completa

### Evidências coletadas do servidor

| Verificação | Resultado |
|---|---|
| Memories versão | 8.1.0 |
| Emite `LoadAdditionalScriptsEvent`? | **NÃO** |
| Usa `window._nc_fileactions`? | **NÃO** — apenas presente em sourcemaps (bundled dep) |
| Usa `registerFileAction()`? | **NÃO** — não invocado no runtime |
| Tem plugin API? | **NÃO** |
| Tem extension point? | **NÃO** |
| Tem custom action registry? | **NÃO** — ações hardcoded em Vue templates |
| PHP events | `NodeWrittenEvent`, `NodeDeletedEvent`, `BeforeTemplateRenderedEvent`, `UserLoggedOutEvent` |
| JS architecture | SPA completa (memories-main.js), webpack, Vue 2/3 |
| Menus de ação | Hardcoded no Vue template (download, delete, edit, share, favorite, move to album, tag) |
| Seleção múltipla | SIM — usa IDs internos (fileid) |

### Análise do código Memories

O Memories é uma aplicação completamente auto-contida:
- Não usa o Files app como base
- Não integra com o sistema de FileActions do `@nextcloud/files`
- Suas ações (download, delete, share, favorite, etc.) são Vue components renderizados condicionalmente
- Não oferece nenhum mecanismo para apps de terceiros registrarem ações
- Não emite eventos que permitam extensão
- Usa `BeforeTemplateRenderedEvent` apenas para injetar SEU PRÓPRIO script

### Possibilidades de integração com Memories

| Nível | Método | Viabilidade | Risco |
|---|---|---|---|
| A — API oficial | Não existe | ❌ | — |
| B — API pública não documentada | Não encontrada | ❌ | — |
| C — Evento Nextcloud | `BeforeTemplateRenderedEvent` para injetar JS globalmente | ⚠️ Parcial | Script carrega mas sem hook para adicionar ação |
| D — JS injection (DOM manipulation) | Injetar botão no menu de seleção do Memories via MutationObserver | ⚠️ Hack frágil | Quebra com qualquer update do Memories |
| E — Fork/PR no Memories | Adicionar extension point ao Memories | Possível | Alto custo de manutenção |

---

## 4. Matriz de Compatibilidade

| Contexto | API suportada | Método atual funciona? | Método recomendado | Batch | Segurança | Risco |
|---|---|---|---|---|---|---|
| **Files** | `@nextcloud/files` FileAction + `LoadAdditionalScriptsEvent` | ✅ SIM | Manter (corrigir execBatch binding) | ✅ | ✅ (getUserFolder) | Baixo |
| **Photos** | Nenhuma API de extensão | ❌ NÃO | JS injection via `BeforeTemplateRenderedEvent` (NÍVEL D) | Possível | ✅ (mesmo backend) | Alto — frágil |
| **Memories** | Nenhuma API de extensão | ❌ NÃO | JS injection via `BeforeTemplateRenderedEvent` (NÍVEL D) | Possível | ✅ (mesmo backend) | Muito alto — frágil |

### Classificação

| App | Nível de integração possível |
|---|---|
| Files | **NÍVEL A** — API oficial, suportada, estável |
| Photos | **NÍVEL D** — JS injection, hack frágil, não recomendado |
| Memories | **NÍVEL D** — JS injection, hack frágil, não recomendado |

---

## 5. Análise de Segurança

### Backend (import endpoint)

| Vetor | Status | Detalhe |
|---|---|---|
| Autenticação | ✅ | Sessão NC obrigatória (não `#[PublicPage]`) |
| CSRF | ✅ | Não tem `#[NoCSRFRequired]` — requer requesttoken |
| Path traversal | ✅ | `getUserFolder()->get()` confina ao home do usuário |
| Cross-user | ✅ | `getUserFolder($userId)` usa o UID da sessão |
| Vault locked | ✅ | `VaultSessionMiddleware` bloqueia se vault locked |
| Symlinks | ✅ | NC filesystem abstraction resolve symlinks dentro do user home |
| External storage | ⚠️ | Funciona — se o user tem acesso, pode importar de external storage |
| Arquivo inexistente | ✅ | `NotFoundException` → 400 |
| MIME spoofing | ✅ | Backend usa `finfo` (não confia no client) |
| Arquivo grande | ✅ | Verifica `$vault->getMaxFileSizeMb()` |
| Temp file cleanup | ✅ | `finally { unlink($tmpFile) }` |

### Conceito de Vault

| Questão | Resposta |
|---|---|
| Arquivo é copiado ou movido? | **Copiado** — original permanece em Files |
| Original continua acessível? | SIM — não remove da source |
| Vault continua protegido? | SIM — AppData isolamento mantido |
| Photos/Memories acessam vault? | NÃO — AppData invisível |
| Thumbnails vazam? | NÃO — gerados internamente no AppData |
| Import sem unlock? | NÃO — middleware bloqueia |
| Cache externo? | NÃO — temp file apagado no finally |

---

## 6. Arquitetura Recomendada

### Para Files (manter + corrigir)

```
Files app
   ↓ LoadAdditionalScriptsEvent
xfiles-init.js
   ↓ registerFileAction
FileAction "Send to X-Files"
   ↓ exec/execBatch
POST /apps/xfiles/api/v1/images/import
   ↓ VaultSessionMiddleware
ImageService.importFromUserFiles()
   ↓ getUserFolder → finfo → upload pipeline
Vault (AppData)
```

**Status:** Funcional. Corrigir bug de `execBatch` binding.

### Para Photos e Memories

**Recomendação: NÃO implementar integração via JS injection.**

Motivo:
- Nenhum mecanismo oficial existe
- JS injection dependeria de seletores CSS/Vue internos que mudam a cada update
- Custo de manutenção desproporcional ao benefício
- Alternativa para o usuário: usar o Files app para enviar imagens ao vault

### Alternativa futura (se Photos/Memories adicionarem plugin API)

Monitorar:
- https://github.com/nextcloud/photos — issues sobre extension API
- https://github.com/pulsejet/memories — issues sobre plugin system
- Nextcloud developer updates para NC 35+

Se uma API for adicionada, implementar via adapter pattern:

```
XFilesFilesAdapter      → registerFileAction (existente)
XFilesPhotosAdapter     → futuro (quando API existir)
XFilesMemoriesAdapter   → futuro (quando API existir)
```

---

## 7. Seleção Múltipla

| App | Representação | Identificador seguro | Batch possível |
|---|---|---|---|
| Files | `Node[]` com `path`, `basename`, `mime`, `fileid` | `path` (relativo ao user home) | SIM via `execBatch` |
| Photos | `fileIds` (internos, array de integers) | `fileid` | N/A (sem integração) |
| Memories | `fileid` (internos) | `fileid` | N/A (sem integração) |

**Recomendação para o backend:** Aceitar `path` (atual) OU `fileId`. O backend pode resolver ambos via `IRootFolder`.

---

## 8. Plano de Implementação

### Fase 1 — Corrigir Files (prioridade alta)

1. Corrigir `execBatch` binding (`this` issue)
2. Considerar aceitar `fileId` como alternativa a `path`
3. Validar que funciona com seleção múltipla no Files
4. Testar com vault locked (mensagem clara)
5. Testar com vault unlocked (import funciona)

### Fase 2 — Photos (NÃO IMPLEMENTAR)

Documentar como limitação conhecida. O usuário pode:
- Abrir a imagem no Files app e usar "Send to X-Files" lá
- Abrir o X-Files e fazer upload direto

### Fase 3 — Memories (NÃO IMPLEMENTAR)

Mesma recomendação que Photos. Documentar como limitação.

### Fase 4 — Segurança

Já implementada e validada (ver seção 5).

### Fase 5 — Testes

Para Files (única integração funcional):

| Teste | Resultado esperado |
|---|---|
| 1 imagem JPG | Toast success, imagem no vault |
| Múltiplas imagens (batch) | Toast com count, todas no vault |
| HEIC | Success (se GD suporta) ou error clear |
| Arquivo não-imagem (.pdf) | Ação não aparece (MIME filter no `enabled`) |
| Vault locked | Error toast "Vault is locked" |
| Arquivo inexistente | Error toast |
| Sessão expirada | Redirect to login |

---

## 9. DevNull — Ícone na Área de Configurações

### Causa raiz

O SVG do DevNull (`/opt/devnull/app/img/app.svg`) usa:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff">
```

`fill="#fff"` (branco fixo) funciona corretamente na **navegação lateral** porque:
- O header/sidebar tem fundo escuro (tema padrão)
- NC aplica `filter: var(--background-invert-if-dark)` nos ícones da nav

Mas na **área de configurações administrativas**, o ícone é renderizado em um contexto com fundo **claro** (branco), sem o filtro de inversão. Resultado: branco sobre branco = invisível.

### Comparação com app oficial

O Activity app (`/var/www/nextcloud/apps/activity/img/activity.svg`) também usa `fill:#fff`. Porém, quando o NC serve ícones na área de settings, ele os processa através de um endpoint de theming (`/apps/theming/img/`) que pode aplicar colorização.

A diferença: ícones registrados via `info.xml` na section `<settings>` são processados pelo theming endpoint que substitui a cor. Ícones na navegação são servidos diretamente.

### Solução recomendada

Usar `fill="currentColor"` no SVG em vez de `fill="#fff"`. Isso permite que o CSS do NC controle a cor do ícone conforme o contexto:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
  <path d="M7 2V4H10V8H8V10H10V12L7 14V22H17V14L14 12V10H16V8H14V4H17V2H7M9 4H15V8H13V10H14V11.46L17 13.46V20H7V13.46L10 11.46V10H11V8H9V4Z"/>
</svg>
```

**Impacto:**
- Navegação sidebar (fundo escuro): `currentColor` herda a cor do texto do nav (branco) → ícone branco ✅
- Settings admin (fundo claro): `currentColor` herda a cor do texto (preto/escuro) → ícone visível ✅
- Tema escuro: funciona em ambos contextos ✅

### Plano de correção (DevNull)

1. Alterar `fill="#fff"` → `fill="currentColor"` em `/opt/devnull/app/img/app.svg`
2. Testar: navegação sidebar (deve permanecer visível)
3. Testar: admin settings (deve ficar visível agora)
4. Testar: tema escuro
5. Commit e deploy

### Nota para X-Files

O X-Files SVG (`/opt/xfiles/app/img/app.svg`) usa `stroke="#ffffff"` — tem o mesmo problema potencial. Deve ser corrigido para `currentColor` também.

---

## 10. Decisões que Precisam de Aprovação

1. **Photos integration:** Confirmar que NÃO implementamos (NÍVEL D — hack frágil). Aceitar como limitação documentada?

2. **Memories integration:** Confirmar que NÃO implementamos (NÍVEL D — hack frágil). Aceitar como limitação documentada?

3. **Se quiser forçar integração Photos/Memories:** Aprovar NÍVEL D (JS injection via `BeforeTemplateRenderedEvent` + MutationObserver para injetar botão no DOM). Entendendo que quebra com qualquer update dessas apps.

4. **DevNull icon:** Aprovar correção `fill="#fff"` → `fill="currentColor"`?

5. **X-Files icon:** Aplicar mesma correção (`stroke="#ffffff"` → `stroke="currentColor"`)?

6. **Backend: aceitar fileId além de path?** Mais robusto para futura integração, mas complexidade extra agora.

---

## 11. Resumo Executivo

| Item | Decisão |
|---|---|
| Files integration | ✅ Funciona — corrigir bug minor no execBatch |
| Photos integration | ❌ Impossível sem hack — NÍVEL D |
| Memories integration | ❌ Impossível sem hack — NÍVEL D |
| Segurança do backend | ✅ Sólida |
| DevNull icon | Corrigir com `currentColor` |
| X-Files icon | Corrigir com `currentColor` |

A afirmação original "Photos usa a mesma infraestrutura do Files" estava **INCORRETA**. Photos tem sua própria SPA Vue que não lê do registro global de FileActions.
