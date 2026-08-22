# X-Files — Decisão Arquitetural v2

**Date:** 2026-08-22
**Status:** Proposta — aguardando aprovação

---

## 1. Files Integration — Decisão

### Arquitetura: MANTER (NÍVEL A — API oficial)

```
Files app
  ↓ LoadAdditionalScriptsEvent
xfiles-init.js
  ↓ registerFileAction (window._nc_fileactions)
Files UI reads registry → shows "Send to X-Files"
  ↓ exec
POST /apps/xfiles/api/v1/images/import
  ↓ VaultSessionMiddleware
ImageService.importFromUserFiles()
```

**Status:** CONFIRMADO que funciona no Files. Correções necessárias no execBatch.

### Correções necessárias

1. **execBatch binding bug:** `this.exec` no callback perde contexto. Corrigir com arrow function ou `.bind(this)`.
2. **Testar batch** com 3+ imagens selecionadas no Files.

---

## 2. Photos Integration — Decisão

### CAMINHO A — API oficial existente

**CONFIRMADO: NÃO EXISTE.**

Evidências:
- Photos 7.0.0 não emite `LoadAdditionalScriptsEvent`
- Photos não lê de `window._nc_fileactions`
- Photos não usa `getFileActions()`
- Photos não tem plugin API
- Nenhuma issue no GitHub nextcloud/photos solicita action extension API
- Ações são Vue components hardcoded (ActionFavorite, etc.)

### CAMINHO B — Extensão upstream

**PROVÁVEL que NÃO seria aceita a curto prazo.**

Não encontrei issues ou PRs no nextcloud/photos solicitando uma API de extensão para ações de terceiros. O Photos é mantido pela Nextcloud GmbH e segue um modelo de desenvolvimento fechado (sem plugin system).

Uma proposta upstream exigiria:
- Issue detalhada com use case
- Implementação de um action registry no Photos (semelhante ao do Files)
- Revisão e merge pelo time da Nextcloud
- Tempo estimado: 6-12 meses (se aceito)

### CAMINHO C — Integração indireta

**DESCOBERTA PARCIAL:**

Photos dispatch `LoadViewer` event (OCA\Viewer\Event\LoadViewer). Nosso script PODE ser carregado no contexto do Photos se escutarmos esse evento. PORÉM, o script sendo carregado não resolve o problema — Photos não consulta `window._nc_fileactions` para renderizar ações.

Mecanismos investigados:

| Mecanismo | Funciona? | Detalhe |
|---|---|---|
| `LoadViewer` event | Script carrega ✅ | Mas Photos não lê FileActions ❌ |
| NC Event Bus | Não há evento de "file selected" global | ❌ |
| OCS APIs | Existem para listar fotos | Não ajuda na UI |
| Deep links | Possível: `nextcloud://xfiles/import?fileId=123` | Não suportado pelo NC web |
| Clipboard | Não aplicável | ❌ |

### DECISÃO PARA PHOTOS

**Opção 1 — Não integrar atualmente (RECOMENDADO)**

Documentar como limitação. O usuário pode:
- Abrir Files → localizar imagem → "Send to X-Files"
- Abrir X-Files → Upload direto
- Arrastar imagem para o X-Files (drag-and-drop)

**Justificativa:** Qualquer integração com Photos seria NÍVEL D (hack frágil). O custo de manutenção supera o benefício. Quando o Photos adicionar uma API de extensão (se adicionar), implementamos via adapter.

---

## 3. Memories Integration — Decisão

### Investigação completa

**CONFIRMADO: NENHUMA API de extensão existe no Memories 8.1.0.**

Evidências:
- Memories não emite `LoadAdditionalScriptsEvent`
- Memories não lê de `window._nc_fileactions`
- Memories usa `BeforeTemplateRenderedEvent` apenas para carregar seus próprios scripts
- Nenhum plugin system, action registry, ou extension point no código
- GitHub issues: nenhuma solicitação de plugin API de terceiros encontrada
- Ações hardcoded: download, delete, edit metadata, share, favorite, move, tag

### DECISÃO PARA MEMORIES

**Opção 1 — Não integrar atualmente (RECOMENDADO)**

Mesma justificativa que Photos.

---

## 4. fileId vs path — Decisão

### Análise

| Critério | `path` | `fileId` |
|---|---|---|
| Estabilidade | Pode mudar (rename) | Estável (imutável no lifecycle do arquivo) |
| Segurança | `getUserFolder()->get(path)` confina ao user | `getUserFolder()->getById(id)` retorna `[]` se não pertence ao user |
| Disponibilidade no Node | Sempre presente | Sempre presente (`$node->getId()`) |
| Cross-user | Impossível (getUserFolder é scoped) | Impossível (getById é scoped ao folder) |
| External storage | Funciona | Funciona |
| Shares | Funciona (se user tem acesso) | Funciona |
| Symlinks | Resolvidos pelo NC | N/A |
| Futuro (Photos/Memories) | Memories usa fileId internamente | Mais natural para futuras integrações |

### DECISÃO: Aceitar AMBOS, preferir fileId

```
POST /apps/xfiles/api/v1/images/import

Body (opção A — fileId):
{ "fileId": 12345 }

Body (opção B — path, fallback):
{ "path": "/Photos/image.jpg" }
```

Backend resolve:
```php
if ($fileId !== null) {
    $nodes = $userFolder->getById($fileId);
    if (empty($nodes)) throw NotFound;
    $node = $nodes[0];
} elseif ($path !== null) {
    $node = $userFolder->get($path);
} else {
    throw BadRequest;
}
```

**Justificativa:** fileId é mais robusto. Path mantido como fallback para compatibilidade. O frontend pode enviar o que tiver disponível.

### Segurança do fileId

| Vetor | Resultado |
|---|---|
| User A envia fileId de User B | `getUserFolder('A')->getById(fileId)` retorna `[]` → 404 **SEGURO** |
| fileId inválido | Retorna `[]` → 404 **SEGURO** |
| fileId de arquivo deletado | Retorna `[]` → 404 **SEGURO** |
| fileId sem permissão leitura | NC filesystem abstraction bloqueia → Exception **SEGURO** |
| fileId de external storage | Funciona se user tem mount → **SEGURO** |

**CONFIRMADO:** `Folder::getById()` é naturalmente scoped ao folder do usuário. Não expõe arquivos de outros usuários.

---

## 5. UX Ideal (quando integração futura existir)

```
Qualquer app (Files/Photos/Memories)
   ↓
Selecionar 1+ imagem(ns)
   ↓
"Send to X-Files"
   ↓
Vault locked?
├── SIM → Toast: "Vault is locked. Open X-Files and unlock first."
└── NÃO
     ↓
     Import pipeline (por arquivo)
     ↓
     Resultado:
     ├── Todos OK → Toast: "N image(s) sent to X-Files vault"
     └── Parcial → Toast: "8/10 imported. 2 failed: [reasons]"
```

### Erros parciais em batch

Comportamento: **continuar processando todos, reportar ao final.**

```json
{
  "success": true,
  "imported": 8,
  "failed": 2,
  "errors": [
    {"name": "file1.jpg", "error": "File type not allowed"},
    {"name": "file2.raw", "error": "File exceeds maximum size"}
  ]
}
```

---

## 6. Vault Isolation — Confirmação

A integração NÃO quebra o modelo de segurança:

```
Files/Photos/Memories  →  Import API  →  Vault (AppData)
       ↑                        ↑              ↑
  Lê do filesystem      Valida + copia     Isolado
  do usuário             para AppData       do filesystem
```

- O import é uma CÓPIA (original permanece)
- O vault continua em AppData (invisível)
- Nenhum app ganha acesso ao vault
- A API requer vault unlocked (VaultSessionMiddleware)

---

## 7. DevNull Icon — Correção Validada

### Causa raiz CONFIRMADA

O DevNull tem DOIS SVGs:
- `app.svg`: `fill="#fff"` — para navegação (fundo escuro) ✅
- `app-dark.svg`: `fill="#fff"` — para settings (fundo claro) ❌ **BUG**

A convenção NC é:
- `app.svg` = branco (para nav sidebar com fundo escuro)
- `app-dark.svg` = preto (para settings com fundo claro)

### Comparação com apps oficiais

| App | app.svg | app-dark.svg (settings) | Settings funciona? |
|---|---|---|---|
| Activity | `fill:#fff` | sem fill (preto default) | ✅ |
| ServerInfo | `stroke:#fff` | `stroke:#000` | ✅ |
| **DevNull** | `fill:#fff` | `fill:#fff` ❌ | ❌ Invisível |

### Correção

`/opt/devnull/app/img/app-dark.svg`:
```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
  <path fill="#000" d="M7 2V4H10V8H8V10H10V12L7 14V22H17V14L14 12V10H16V8H14V4H17V2H7M9 4H15V8H13V10H14V11.46L17 13.46V20H7V13.46L10 11.46V10H11V8H9V4Z"/>
</svg>
```

Mudar `fill="#fff"` → `fill="#000"`.

O `app.svg` (branco) permanece inalterado — funciona corretamente na nav.

**NÃO usar `currentColor`** — a convenção NC é cores fixas em dois ficheiros separados.

---

## 8. X-Files Icon — Análise

### Estado atual

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32">
  <rect ... fill="none" stroke="#ffffff" stroke-width="1.5"/>
  <path ... fill="none" stroke="#ffffff" stroke-width="2" .../>
  <path ... fill="none" stroke="#ffffff" stroke-width="2" .../>
</svg>
```

### Problema

O X-Files atualmente NÃO tem settings page, portanto NÃO tem `app-dark.svg`. Quando adicionarmos settings, precisaremos:

1. Criar `app-dark.svg` com `stroke="#000000"`
2. Manter `app.svg` com `stroke="#ffffff"` (para nav)

### Verificação no NC atual

- Navegação sidebar: ícone branco sobre fundo escuro → ✅ correto
- Settings: não tem section registrada → N/A (ainda)

**Quando criarmos a settings page, criar o `app-dark.svg`.**

---

## 9. X-Files Settings — Arquitetura

### Documentação oficial (NC 34 Developer Manual)

Referência: [Settings](https://docs.nextcloud.com/server/stable/developer_manual/basics/setting.html)

Estrutura:
- `lib/Sections/AdminSection.php` — implements `IIconSection` (sidebar entry)
- `lib/Settings/AdminSettings.php` — implements `ISettings` (form content)
- `info.xml` — `<settings><admin>` + `<admin-section>`

### Decisão: Admin + Personal

```
Settings
├── Administration
│   └── X-Files (admin-only configs)
└── Personal
    └── X-Files (user preferences, se necessário no futuro)
```

Para o MVP: apenas **Admin**.

---

## 10. Configurações Concretas

### Análise do código atual

| Configuração | Admin/User | Existe hoje? | Onde está hoje? | Deveria existir? | Tipo |
|---|---|---|---|---|---|
| Habilitar/desabilitar X-Files por grupo | Admin | ❌ | — | ✅ SIM | multi-select groups |
| Limite máximo de arquivo (global override) | Admin | ❌ | — | ✅ SIM | int (MB) |
| Default auto-lock timeout | Admin | ❌ | — | ✅ SIM | select (seconds) |
| Permitir reset via conta NC | Admin | ❌ | — | ✅ SIM | boolean |
| Contagem de vaults ativos | Admin | ❌ | — | ✅ SIM (readonly info) | display |
| Storage total usado | Admin | ❌ | — | ✅ SIM (readonly info) | display |
| Auto-lock timeout (user) | User | ✅ | Settings modal dentro do vault | Manter lá | select |
| Max file size (user) | User | ✅ | Settings modal dentro do vault | Manter lá (respeitando admin max) | int |
| Senha do vault | User | ✅ | Settings modal dentro do vault | Manter lá | — |

### Configuração ≠ Acesso ao Vault

```
Admin Settings page:
  - Configurar limites globais
  - Ver estatísticas
  - Habilitar/desabilitar
  - NÃO acessar conteúdo de vaults
  - NÃO desbloquear vaults de usuários
  - NÃO ver imagens

Vault (dentro do app):
  - Unlock com senha
  - Ver/gerenciar imagens
  - Configurações pessoais do vault
```

**O admin NÃO pode ver o conteúdo dos vaults pela settings page.** Apenas configurar limites e ver métricas agregadas.

---

## 11. Decisão Final — Photos e Memories

### DECISÃO: Opção 1 — Não integrar atualmente

**Justificativa técnica:**
- Nenhuma API oficial existe (CONFIRMADO)
- Nenhuma API não-documentada estável encontrada (CONFIRMADO)
- Nenhuma demanda upstream por plugin system encontrada
- JS injection seria NÍVEL D (quebra com qualquer update)
- Custo de manutenção >>> benefício
- O usuário tem alternativa funcional (Files app, upload direto, drag-drop)

**Documentação para o usuário:**

> "Send to X-Files" is available in the Files app. For images viewed in Photos or Memories, open the image location in Files to use this action, or upload directly to X-Files.

**Monitorar para futuro:**
- nextcloud/photos: qualquer issue sobre action extension API
- pulsejet/memories: qualquer issue sobre plugin system
- NC 35+ release notes: qualquer menção a unified action API

---

## 12. Plano de Implementação por Fases

### Fase A — Correções imediatas

1. Fix `execBatch` binding bug em `src/init.js`
2. Fix DevNull icon: `app-dark.svg` → `fill="#000"`
3. Create X-Files `app-dark.svg` com `stroke="#000000"`

### Fase B — Backend: fileId support

1. Alterar `ImageController::import()` para aceitar `fileId` OU `path`
2. Resolver fileId via `getUserFolder()->getById()`
3. Batch endpoint com relatório de erros parciais
4. Testes de segurança (cross-user fileId)

### Fase C — Files consolidation

1. Atualizar `init.js` para enviar `fileId` quando disponível (Node tem `.fileid`)
2. Fix execBatch
3. Testar batch com 1, 3, 10 imagens
4. Testar com vault locked/unlocked

### Fase D — Admin Settings

1. `lib/Sections/AdminSection.php` (IIconSection)
2. `lib/Settings/AdminSettings.php` (ISettings)
3. `app-dark.svg` (ícone para settings)
4. Template Vue para admin settings
5. Backend para salvar/ler configurações via `IAppConfig`
6. Registrar em `info.xml`

### Fase E — Testes

| Teste | Cenário |
|---|---|
| Files single | 1 JPG → vault |
| Files batch | 5 imagens → vault |
| Files HEIC | Se suportado pelo GD |
| Files non-image | Ação não aparece |
| Vault locked | Toast error |
| fileId inválido | 404 |
| fileId cross-user | 404 (seguro) |
| Admin settings | Valores salvos e aplicados |
| User exceeds admin limit | Bloqueado |

### Fase F — Documentação

1. README: documentar limitação Photos/Memories
2. CHANGELOG: atualizar com v0.3 changes
3. Push e run test suite

---

## 13. Respostas às 10 Perguntas

### 1. Melhor arquitetura para Files?
`registerFileAction` via `LoadAdditionalScriptsEvent`. **CONFIRMADO como API oficial.** Manter.

### 2. Melhor arquitetura para Photos?
**Nenhuma integração** — sem API. Documentar como limitação.

### 3. Melhor arquitetura para Memories?
**Nenhuma integração** — sem API. Documentar como limitação.

### 4. path, fileId ou ambos?
**Ambos.** fileId preferido, path como fallback. Backend resolve via getUserFolder.

### 5. Devemos tentar integração upstream?
**Não agora.** Monitorar. Se Photos/Memories adicionarem API no futuro, implementar adapter.

### 6. Devemos rejeitar JS injection?
**SIM.** NÍVEL D. Frágil. Custo de manutenção inaceitável.

### 7. Como o X-Files deve aparecer nas configurações?
`Settings → Administration → X-Files` com IIconSection + ISettings.

### 8. Quais configurações devem existir?
Admin: enable/disable por grupo, max file size global, default timeout, allow NC password recovery, stats display.

### 9. Como corrigir os ícones?
DevNull: `app-dark.svg` → `fill="#000"` (estava `#fff` — bug).
X-Files: criar `app-dark.svg` com `stroke="#000000"` quando settings page for criada.

### 10. Plano de implementação?
Fase A (fixes) → B (fileId) → C (Files consolidation) → D (Admin Settings) → E (Tests) → F (Docs).
