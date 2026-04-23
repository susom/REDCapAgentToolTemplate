# Copilot Instructions — REDCapAgentToolTemplate

## What This Is

Starter template for building SecureChatAI agent tool External Modules. Copy, rename, replace the example tools.

## Architecture

Tool EMs have three parts that must stay in sync:
- `config.json` → `api-actions` (registers REDCap API endpoints)
- `config.json` → `agent-tool-definitions` (JSON Schema tool definitions the LLM reads)
- PHP `redcap_module_api()` switch cases (routes actions to tool methods)

The linking key is the **action name** string — it must be identical in all three places.

## Auto-Discovery

SecureChatAI discovers tool EMs by prefix. Name your module `redcap_agent_*` and include `agent-tool-definitions` in config.json.

## Adding a Tool

1. Add `api-actions` entry in config.json: `"my_action": {"description": "...", "access": ["auth"]}`
2. Add `agent-tool-definitions` entry with JSON Schema params and `"api-action": "my_action"`
3. Add switch case: `case "my_action": return $this->wrapResponse($this->toolMyAction($payload));`
4. Implement `toolMyAction()` following the validate → try/catch → return pattern

## Conventions

- **Namespace:** `Stanford\\REDCapAgentToolTemplate` (update to match your module name)
- **Action naming:** `snake_case` with category prefix (`records_get`, `projects_search`)
- **Tool naming:** `dot.notation` for LLM-facing names (`records.get`, `projects.search`)
- **Error pattern:** Return `["error" => true, "message" => "..."]` — never throw past tool boundaries
- **Logging:** `$this->emDebug()` for debug, `$this->emError()` for errors (requires em_logger EM)

## Code Style

4 spaces, UTF-8, LF line endings. Tabs in config.json.
