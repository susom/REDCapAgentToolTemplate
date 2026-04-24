# Copilot Instructions — REDCapAgentToolTemplate

## What This Is

Starter template for building SecureChatAI agent tool External Modules. Copy, rename, replace the example tools.

## Architecture

Tool EMs have two parts that must stay in sync:
- `tools.json` — Tool manifest with JSON Schema definitions the LLM reads. Each tool has an `action` field.
- PHP `handleToolCall()` switch cases — Routes action strings to tool methods.

The linking key is the **action string** — it must be identical in both places.

`config.json` is for EM registration only (name, namespace, framework-version). No tool definitions live there.

## Auto-Discovery

SecureChatAI discovers tool EMs by prefix. Name your module `redcap_agent_*` and include a `tools.json` manifest.

## Adding a Tool

1. Add tool definition to `tools.json` with JSON Schema params and `"action": "my_action"`
2. Add switch case in `handleToolCall()`: `case "my_action": return $this->toolMyAction($payload);`
3. Implement `toolMyAction()` following the validate → try/catch → return pattern

## Conventions

- **Namespace:** `Stanford\\REDCapAgentToolTemplate` (update to match your module name)
- **Action naming:** `snake_case` with category prefix (`records_get`, `projects_search`)
- **Tool naming:** `dot.notation` for LLM-facing names (`records.get`, `projects.search`)
- **Error pattern:** Return `["error" => true, "message" => "..."]` — never throw past tool boundaries
- **Return format:** Raw PHP arrays — no HTTP wrapping. SecureChatAI handles serialization.
- **Logging:** `$this->emDebug()` for debug, `$this->emError()` for errors (requires em_logger EM)

## Code Style

4 spaces, UTF-8, LF line endings. Tabs in config.json.
