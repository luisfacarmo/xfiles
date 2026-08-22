# X-Files — Diagnostic Report

**Date:** 2026-08-22
**Commit:** 9cf98a2

---

## A. Resumo Executivo

| Problema | Reproduzido? | Causa confirmada? | Evidência | Severidade |
|---|---|---|---|---|
| 1. Bulk Classify não aparece | ✅ SIM | ✅ SIM | NC 34 passa objeto a `enabled()`, não array | **Crítica** |
| 2. Classify individual não funciona | ✅ SIM | ✅ SIM | NC 34 passa objeto a `exec()`, não `(file,view,dir)` | **Crítica** |
| 3. Aba de auditoria não aparece | ✅ SIM | ✅ SIM | Feature NUNCA foi implementada | Baixa |
| 4. Botão checkmark ao lado do Upload | ✅ SIM | ✅ SIM | É o botão "Select multiple" (funcionalidade existente) | Informacional |

---

## B. Problema 1 — Bulk Classify não aparece

### Comportamento

A ação "Classify" não aparece no menu de seleção múltipla quando várias imagens são selecionadas no Files app.

### Causa raiz CONFIRMADA

**Incompatibilidade de API entre `@nextcloud/files` 3.12.2 (TypeScript types) e o runtime real do NC 34.**

A interface TypeScript define:
```typescript
enabled?: (files: Node[], view: View) => boolean
```

Mas o NC 34 Files app **realmente** chama:
```javascript
e.enabled({nodes: [...], view: ..., folder: ..., contents: ...})
```

Nosso código:
```javascript
enabled(files) {
    return files.length > 0 && files.every(...)
}
```

- `files` = `{nodes: [...], view: ..., folder: ..., contents: ...}` (um objeto)
- `files.length` = `undefined`
- `undefined > 0` = `false`
- **Ação desabilitada → nunca mostrada**

### Evidência

Extraído de `/var/www/nextcloud/dist/files-main.js`:
```javascript
e.enabled({nodes:[this.source],view:this.activeView,folder:this.activeFolder,contents:this.nodes})
```

### Arquivos envolvidos

- `app/src/init.js` (linha ~38: `enabled(files)`)

### Correção proposta

```javascript
enabled({ nodes }) {
    return nodes.length > 0 && nodes.every(
        (file) => file.mime && imageMimes.includes(file.mime),
    )
}
```

Aceitar o objeto `{nodes, view, folder, contents}` e extrair `nodes` via destructuring.

---

## C. Problema 2 — Classify individual não funciona

### Comportamento

O ícone de cadeado pode aparecer (se browser tem cache antigo), mas clicar não executa a operação. O arquivo não é movido para o vault.

### Causa raiz CONFIRMADA

**Mesma incompatibilidade de API.** O NC 34 chama:
```javascript
e.exec({nodes: [node], view: ..., folder: ..., contents: ...})
```

Nosso código:
```javascript
async exec(file) {
    const response = await axios.post(url, { path: file.path })
}
```

- `file` = `{nodes: [node], view: ..., folder: ..., contents: ...}`
- `file.path` = `undefined` (o objeto não tem `.path`, está em `.nodes[0].path`)
- Backend recebe `{path: undefined}` → não encontra arquivo → silently fails ou throws

### Evidência

Extraído de `/var/www/nextcloud/dist/files-main.js`:
```javascript
e.exec({nodes:[this.source],view:this.currentView,folder:this.currentFolder,contents:this.dirContents})
```

### Correção proposta para `exec`:

```javascript
async exec({ nodes }) {
    const file = nodes[0]
    const url = generateUrl('/apps/xfiles/api/v1/images/import')
    const response = await axios.post(url, {
        path: file.path,
        fileId: file.fileid,
    })
    // ...
}
```

### Correção proposta para `execBatch`:

```javascript
async execBatch({ nodes }) {
    const results = []
    for (const file of nodes) {
        try {
            const response = await axios.post(url, {
                path: file.path,
                fileId: file.fileid,
            })
            results.push(response.data?.success ? true : false)
        } catch (e) {
            results.push(false)
        }
    }
    // ...
    return results
}
```

### Logs relevantes

Nenhum log server-side seria gerado porque a request nunca chega ao backend corretamente (path=undefined → 400 ou falha de validação que o catch engole).

---

## D. Problema 3 — Aba de auditoria/últimas ações

### Status da implementação

**FEATURE NUNCA FOI IMPLEMENTADA.**

Evidências:
- Nenhum arquivo `*audit*`, `*activity*`, `*history*`, `*log*` encontrado em `src/`
- Nenhum componente Vue de atividade existe
- Nenhuma rota de auditoria no `routes.php`
- Nenhum endpoint backend para listar eventos
- Nenhuma tabela no DB para armazenar eventos de auditoria
- O assunto foi DISCUTIDO no planejamento mas classificado como "planejar, separar storage de UI"

### O que existe hoje

Apenas chamadas ao `LoggerInterface` (NC system logger) que registram:
- Upload success (debug)
- Import integrity failure (error)
- Delete original failure (warning)
- Classify success (info)
- Delete success (debug)
- Vault creation (info)
- Password reset (info)
- Thumbnail failure (warning)

Esses logs vão para o nextcloud.log geral, NÃO para uma interface no vault.

### Correção proposta

Implementar como feature nova em sprint futura:
1. Tabela `oc_xfiles_audit` (event_type, user_id, file_name, timestamp, details)
2. Endpoint `GET /api/v1/audit` (requer vault unlocked)
3. Componente Vue `AuditView.vue` como aba/seção no vault unlocked

---

## E. Problema 4 — Botão checkmark

### Origem e finalidade CONFIRMADAS

O botão é o **"Select multiple"** toggle, implementado no `UnlockedView.vue`:

```html
<NcButton
    type="tertiary"
    :aria-label="t('xfiles', 'Select multiple')"
    :class="{ 'xfiles-unlocked__select-active': selectMode }"
    @click="toggleSelectMode">
    <template #icon>
        <CheckboxMultipleIcon :size="20" />
    </template>
</NcButton>
```

- **Ícone:** `CheckboxMultipleMarked` (parece um checkmark com checkbox)
- **Função:** Alterna o modo de seleção múltipla dentro do vault
- **Handler:** `toggleSelectMode()` → seta `selectMode = true/false`
- **Quando ativo:** Tiles mostram checkboxes, botão "Delete (N)" aparece
- **Quando clicado com vault vazio:** Nada visível acontece (sem imagens para selecionar)

### Por que "nada acontece"

O botão funciona corretamente, mas:
- Se o vault está vazio (sem imagens), ativar selectMode não tem efeito visível
- Não há feedback de "modo ativado" além da mudança de cor do botão (`select-active` class)
- Não há tooltip visível sem hover

### Melhoria proposta

- Adicionar texto "Select" ao lado do ícone quando há imagens
- Ou ocultar o botão quando vault está vazio
- Melhorar feedback visual (e.g., toast "Selection mode enabled")

---

## F. Auditoria do Sistema de Logging

### Estado atual

| Aspecto | Status | Detalhe |
|---|---|---|
| Logger utilizado | `Psr\Log\LoggerInterface` | NC standard logger |
| Nível de log | Variado (debug/info/warning/error) | Adequado |
| Contexto incluído | Parcial | user_id sim, file_id às vezes, operation_id nunca |
| Identificação de operação | 🔴 Ausente | Sem correlation ID |
| Identificação de arquivo | 🟡 Parcial | Às vezes path, às vezes name |
| Identificação do usuário | 🟢 Presente | Via `'user' => $userId` |
| Início/fim de operações | 🔴 Ausente | Só final (success/error) |
| Request ID | 🔴 Ausente | NC tem `reqId` no log mas não correlacionamos |
| Exceções | 🟡 Parcial | Mensagem sim, stack trace não |
| HTTP status | 🔴 Ausente | Controllers não logam responses |
| Frontend errors | 🔴 Ausente | Apenas toasts, nenhum log |
| Operações de filesystem | 🟡 Parcial | Classify loga, upload loga, delete loga |
| disableTrashBin() | 🔴 Ausente | Sem log quando trashbin é bypassed |

### Classificação geral: 🔴 INSUFICIENTE

Os logs atuais NÃO permitiriam diagnosticar os problemas 1 e 2 sem acesso ao debugger do browser. O backend nunca recebe a request (frontend silently fails), e não há logging no frontend.

### Proposta de logging robusto

```php
// Cada operação de import/classify:
$this->logger->info('xfiles.classify.start', [
    'app' => 'xfiles',
    'user' => $userId,
    'fileId' => $fileId,
    'path' => $path,
    'vault_id' => $vault->getId(),
    'vault_state' => 'locked', // or 'unlocked'
]);

// Cada step:
$this->logger->debug('xfiles.classify.step', [
    'app' => 'xfiles',
    'step' => 'copy_to_appdata',
    'fileId' => $fileId,
    'storage_name' => $storageName,
]);

// Final:
$this->logger->info('xfiles.classify.success', [
    'app' => 'xfiles',
    'user' => $userId,
    'fileId' => $fileId,
    'image_id' => $image->getId(),
    'original_deleted' => true,
    'trashbin_bypassed' => true,
    'duration_ms' => $elapsed,
]);
```

---

## G. Plano de Correção

### Problema 1 + 2 (mesma causa raiz)

| Item | Detalhe |
|---|---|
| **Causa raiz** | NC 34 FileAction API runtime passa `{nodes, view, folder, contents}` em vez de `(files, view)` |
| **Correção** | Reescrever `enabled()`, `exec()`, e `execBatch()` para aceitar object destructuring |
| **Arquivos** | `app/src/init.js` |
| **Risco** | Baixo (mudança apenas no frontend, backend inalterado) |
| **Testes** | 1) Classify individual → arquivo movido; 2) Selecionar 3+ → "Classify these files" no menu; 3) Vault locked → funciona |
| **Critério de aceite** | Ação visível em single + bulk; operação completa (move + trashbin bypass) |

### Problema 3

| Item | Detalhe |
|---|---|
| **Causa raiz** | Feature nunca implementada |
| **Correção** | Implementar em sprint futura (tabela audit + endpoint + Vue component) |
| **Risco** | N/A (nova feature) |
| **Critério de aceite** | Tab visível no vault unlocked com últimas 50 ações |

### Problema 4

| Item | Detalhe |
|---|---|
| **Causa raiz** | Botão "Select multiple" sem feedback visual claro quando vault vazio |
| **Correção** | Ocultar botão quando `images.length === 0`; ou adicionar tooltip mais claro |
| **Risco** | Mínimo (cosmético) |
| **Critério de aceite** | Botão não confunde o usuário |

### Logging

| Item | Detalhe |
|---|---|
| **Causa raiz** | Logging insuficiente para diagnosticar falhas frontend↔backend |
| **Correção** | Structured logging com operation steps + considerar audit table futura |
| **Risco** | Baixo |
| **Critério de aceite** | Uma operação de classify pode ser reconstruída apenas pelos logs |
