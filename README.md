# REDCap Agent Template

A **starter template** for building SecureChatAI agent tool External Modules.

Copy this module, rename it, replace the example tools with your own, and SecureChatAI will auto-discover it.

---

## Quick Start

```bash
# 1. Copy the template
cp -r redcap_agent_tool_template_v9.9.9 redcap_agent_yourtools_v9.9.9

# 2. Rename the PHP class file
mv redcap_agent_yourtools_v9.9.9/REDCapAgentToolTemplate.php \
   redcap_agent_yourtools_v9.9.9/REDCapAgentYourTools.php

# 3. Find-and-replace in all files:
#    REDCapAgentToolTemplate  →  REDCapAgentYourTools
#    (class name, namespace, config.json "name")

# 4. Update emLoggerTrait.php namespace to match

# 5. Replace the example tools with your own (see below)

# 6. Enable the EM in REDCap → SecureChatAI auto-discovers it
```

---

## How Auto-Discovery Works

SecureChatAI scans for enabled EMs whose prefix starts with **`redcap_agent_`** and reads their `agent-tool-definitions` from config.json. That's it.

**Requirements for auto-discovery:**
1. Module directory named `redcap_agent_<something>_v*`
2. config.json contains `agent-tool-definitions` array
3. Module is enabled **system-wide** in REDCap (project-level enablement is not required)
4. EM prefix listed in SecureChatAI's **Agent Tool EM Prefixes** (system or project level)

**Internal use:** Tools are invoked via direct PHP calls (EM-to-EM, same process). No API tokens, no HTTP, no network overhead — just one EM calling another's `redcap_module_api()` method.

**External use:** Tools can also be called over HTTP via the REDCap API. Enable the module on a project, generate an API token, and configure SecureChatAI with the token and API URL.

---

## The Three Things That Must Stay In Sync

Every tool EM has three parts. If any of them are out of sync, the tool won't work:

```
config.json                         PHP Class
┌─────────────────────┐            ┌─────────────────────────┐
│                     │            │                         │
│ 1. "api-actions": { │ ───maps──→ │ 3. redcap_module_api()  │
│    "my_action": {}  │            │      case "my_action":  │
│    }                │            │        return toolX()   │
│                     │            │                         │
│ 2. "agent-tool-     │            └─────────────────────────┘
│     definitions":   │
│    [{               │ ───tells LLM──→ what tools exist
│      "api-action":  │                  and how to call them
│        "my_action"  │
│    }]               │
└─────────────────────┘
```

**1. `api-actions`** — Registers the endpoint with REDCap. Without this, the API call 404s.

**2. `agent-tool-definitions`** — Tells the LLM the tool exists and how to call it. Without this, the LLM doesn't know the tool is available.

**3. `switch` case in `redcap_module_api()`** — Routes the action to your PHP method. Without this, the call returns "Unknown action".

**The linking key is the action name** (e.g., `"my_action"`). It must be identical in all three places.

---

## config.json Deep Dive

This is the part that's not obvious. config.json serves double duty: it configures the EM for REDCap **and** defines the tool schemas for the LLM.

### `api-actions` — Registering Endpoints

```json
"api-actions": {
    "example_greet": {
        "description": "Agent tool: return a greeting for the given name",
        "access": ["auth"]
    }
}
```

| Field | What it does |
|-------|-------------|
| Key (`"example_greet"`) | The action string passed to `redcap_module_api()`. This is what you switch on. |
| `description` | Human-readable, for documentation only. Not shown to the LLM. |
| `access` | Always `["auth"]` — requires a valid API token. |

**Naming convention:** `category_action` in snake_case. Examples: `records_get`, `projects_search`, `files_upload`, `reports_generate`.

### `agent-tool-definitions` — Teaching the LLM

This is an array of tool definitions. Each one tells the LLM:
- **What the tool is called** (the name the LLM uses when making a tool call)
- **What it does** (the LLM reads this to decide when to use it)
- **What parameters it accepts** (JSON Schema format)
- **Which API action to call** (links back to `api-actions`)

Here's a fully annotated example:

```json
{
    "name": "example.greet",
    //       ^^^^^^^^^^^^^^
    //       LLM-facing name. Use dot.notation: "category.action"
    //       This is what appears in the LLM's tool call.

    "description": "Return a personalized greeting message for the given name.",
    //              ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
    //              THE MOST IMPORTANT FIELD. The LLM reads this to decide
    //              whether to use this tool. Be specific and clear.
    //              Bad:  "Greet someone"
    //              Good: "Return a personalized greeting message for the given name."

    "api-action": "example_greet",
    //             ^^^^^^^^^^^^^^
    //             Must EXACTLY match a key in "api-actions" above.
    //             This is the glue between the LLM schema and the PHP router.

    "parameters": {
        "type": "object",
        //       ^^^^^^ Always "object" at the top level.

        "properties": {
            "name": {
                "type": "string",
                //       ^^^^^^ JSON Schema types: string, integer, number, boolean, array, object
                "description": "The name to greet"
                //              ^^^^^^^^^^^^^^^^^
                //              LLM reads this to know what value to pass.
            },
            "formal": {
                "type": "boolean",
                "description": "If true, use formal greeting style",
                "default": false
                //          ^^^^^ Tells the LLM the default if omitted.
            }
        },

        "required": ["name"]
        //           ^^^^^^ Which params the LLM MUST provide.
        //           Omitted params are optional.
    },

    "readOnly": true,
    //          ^^^^ Hint to the orchestrator:
    //               true  = this tool only reads data (safe to call freely)
    //               false = this tool modifies data

    "destructive": false
    //             ^^^^^ Hint to the orchestrator:
    //                   true  = this tool deletes or irreversibly modifies data
    //                   false = safe or reversible
}
```

### Parameter Types Reference

| JSON Schema Type | PHP Type After Casting | Example |
|---|---|---|
| `"string"` | string | `"name": {"type": "string"}` |
| `"integer"` | int | `"pid": {"type": "integer"}` |
| `"number"` | float | `"amount": {"type": "number"}` |
| `"boolean"` | bool | `"overwrite": {"type": "boolean", "default": false}` |
| `"array"` of strings | array | `"fields": {"type": "array", "items": {"type": "string"}}` |
| `"object"` | assoc array | `"data": {"type": "object", "description": "Key-value pairs"}` |

### Adding an Enum (Fixed Choices)

```json
"status": {
    "type": "string",
    "enum": ["active", "inactive", "archived"],
    "description": "Filter by status"
}
```

---

## PHP Implementation Pattern

Every tool method follows the same 5-step structure:

```php
public function toolMyAction(array $payload)
{
    // 1. VALIDATE — Check required params, return error array if missing
    if (empty($payload['pid'])) {
        return [
            "error" => true,
            "message" => "Missing required parameter: pid"
        ];
    }

    // 2. EXTRACT — Pull out params with defaults and type casting
    $pid = (int)$payload['pid'];
    $limit = (int)($payload['limit'] ?? 10);
    $fields = $payload['fields'] ?? null;  // optional array

    try {
        // 3. EXECUTE — Call REDCap methods or do your work
        $result = \REDCap::getData($pid, 'json', null, $fields);

        // 4. RETURN — Structured result (no "error" key = success)
        return [
            "pid" => $pid,
            "record_count" => count($result),
            "records" => $result
        ];
    } catch (\Exception $e) {
        // 5. ERROR — Log it, return error array (never throw past this boundary)
        $this->emError("myAction error for pid $pid: " . $e->getMessage());
        return [
            "error" => true,
            "message" => "Failed to do the thing: " . $e->getMessage()
        ];
    }
}
```

**Rules:**
- **Never throw exceptions** past the tool method boundary — always return `["error" => true, ...]`
- **Always validate** required params before doing anything
- **Always try-catch** the core logic
- **`"error" => true`** in the return array triggers HTTP 400 via `wrapResponse()`
- **Log errors** with `$this->emError()`, debug info with `$this->emDebug()`

---

## `redcap_module_api()` — The Router

This is the single entry point. REDCap calls it when an API request arrives with `content=externalModule` and your module's prefix.

```php
public function redcap_module_api($action = null, $payload = [])
{
    // ... payload normalization (copy from template) ...

    switch ($action) {
        case "example_greet":                    // ← matches api-actions key
            return $this->wrapResponse(
                $this->toolGreet($payload)       // ← your tool method
            );

        case "example_add":
            return $this->wrapResponse(
                $this->toolAdd($payload)
            );

        default:
            return $this->wrapResponse([
                "error" => true,
                "message" => "Unknown action: $action"
            ], 400);
        }
}
```

**Payload normalization:** The top of this method handles the various ways REDCap might pass the payload (nested JSON string, POST params, raw body). Copy it as-is from the template — you shouldn't need to modify it.

---

## Adding a New Tool — Checklist

Every time you add a tool, you touch exactly 3 places:

### ☐ 1. config.json → `api-actions`

```json
"my_action": {
    "description": "Agent tool: what it does",
    "access": ["auth"]
}
```

### ☐ 2. config.json → `agent-tool-definitions`

```json
{
    "name": "category.myAction",
    "description": "Clear description the LLM will read",
    "api-action": "my_action",
    "parameters": { ... },
    "readOnly": true,
    "destructive": false
}
```

### ☐ 3. PHP → switch case + tool method

```php
case "my_action":
    return $this->wrapResponse(
        $this->toolMyAction($payload)
    );
```

**Verify the action string is identical in all three places.**

---

## Testing Your Tools

### curl

```bash
curl -X POST https://your-redcap/api/ \
  -d "token=YOUR_TOKEN" \
  -d "content=externalModule" \
  -d "prefix=redcap_agent_tool_template" \
  -d "action=example_greet" \
  -d 'payload={"name":"World","formal":true}'
```

Expected response:
```json
{"greeting":"Good day, World. How may I assist you?","formal":true}
```

### Common Mistakes

| Symptom | Cause |
|---------|-------|
| 404 or "module not found" | Module not enabled, or prefix doesn't match directory name |
| "Unknown action: X" | Action string in curl doesn't match `api-actions` key in config.json |
| Tool doesn't appear in LLM | Missing or malformed `agent-tool-definitions` entry |
| LLM calls tool but gets wrong params | `api-action` in tool definition doesn't match `api-actions` key |
| "Invalid JSON in payload" | Payload isn't valid JSON — check quoting in curl |

---

## File Structure

```
redcap_agent_yourtools_v9.9.9/
├── config.json                 ← Tool registration (api-actions + agent-tool-definitions)
├── REDCapAgentYourTools.php    ← Router + tool implementations
├── emLoggerTrait.php           ← Logging helper (copy as-is, update namespace)
└── README.md
```

That's it. No views, no JS, no CSS. Tool EMs are backend-only.

---

## Reference: Complete Example Tool EM

See [`redcap_agent_record_tools`](https://github.com/susom/REDCapAgentRecordTools) for a production example with 8 tools covering project discovery, record CRUD, and survey link generation.
