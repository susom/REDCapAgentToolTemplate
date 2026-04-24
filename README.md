# REDCap Agent Tool Template

A **starter template** for building SecureChatAI agent tool External Modules.

Copy this module, rename it, replace the example tools with your own, and SecureChatAI will auto-discover it.

---

## Why Domain-Grouped Tool EMs?

Tool EMs are organized by domain — e.g., `record_tools`, `webscrape_tools`, `reporting_tools`. Each domain gets its own EM. This keeps things focused:

| Benefit | How |
|---------|-----|
| **Independent deployment** | Push a fix to record tools without touching webscrape tools |
| **Scoped blast radius** | A bug in one domain doesn't affect others |
| **Clean ownership** | Different people can own different tool sets |
| **Selective enablement** | Turn off an experimental tool set without disabling everything |
| **Readable config** | Each EM has a focused, manageable config.json |
| **Portable** | Ship `record_tools` to another institution without dragging unrelated tools along |

**Rule of thumb:** One EM per tool *domain*. If two tools share the same data context and always deploy together, they belong in the same EM.

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

SecureChatAI discovers tool EMs by matching their prefix against the **Agent Tool EM Prefixes** list (configurable at system or project level in SecureChatAI). Any EM whose prefix matches an entry in that list — and has a `tools.json` manifest — will be discovered.

The `redcap_agent_` prefix is a **convention**, not a hard requirement. You could name your module `my_custom_tools_v1.0.0` and it would work fine as long as you add `my_custom_tools` to the prefix list. That said, `redcap_agent_*` is the recommended convention — it makes tool EMs instantly recognizable and easy to group.

**Requirements for auto-discovery:**
1. EM prefix listed in SecureChatAI's **Agent Tool EM Prefixes** (system or project level)
2. Module contains a `tools.json` file with tool definitions
3. Module is enabled **system-wide** in REDCap (project-level enablement is not required)

Tools are invoked via direct PHP calls (EM-to-EM, same process). No API tokens, no HTTP, no network overhead — just one EM calling another's `handleToolCall()` method.

---

## The Two Things That Must Stay In Sync

Every tool EM has two parts that must agree. If they're out of sync, the tool won't work:

```
tools.json                          PHP Class
┌─────────────────────┐            ┌──────────────────────────┐
│                     │            │                          │
│  "action":          │ ───maps──→ │  handleToolCall()        │
│    "my_action"      │            │    case "my_action":     │
│                     │            │      return toolX()      │
│  "name":            │            │                          │
│    "cat.myAction"   │ ─tells LLM→ what tools exist         │
│                     │            │  and how to call them    │
│  "parameters": {}   │            │                          │
└─────────────────────┘            └──────────────────────────┘
```

**1. `tools.json`** — Declares the tool's name, description, parameters, and `action` string. SecureChatAI reads this to build the LLM tool list. Without it, the LLM doesn't know the tool exists.

**2. `switch` case in `handleToolCall()`** — Routes the `action` string to your PHP method. Without this, the call returns "Unknown action".

**The linking key is the `action` string** (e.g., `"my_action"`). It must be identical in both places.

---

## tools.json — The Tool Manifest

This is a separate file (not embedded in config.json) that declares your tools for the LLM. SecureChatAI's auto-discovery reads this file directly.

Here's a fully annotated example:

```json
{
    "tools": [
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

            "action": "example_greet",
            //         ^^^^^^^^^^^^^^
            //         Must EXACTLY match a case in handleToolCall().
            //         This is the glue between the LLM schema and the PHP router.

            "parameters": {
                "type": "object",
                //       ^^^^^^ Always "object" at the top level.

                "properties": {
                    "name": {
                        "type": "string",
                        //       ^^^^^^ JSON Schema types: string, integer, number,
                        //              boolean, array, object
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
    ]
}
```

> **Note:** Comments are shown above for documentation only — JSON does not support comments. See the actual `tools.json` in this repo for the clean version.

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

## config.json — EM Registration Only

With tool definitions moved to `tools.json`, config.json is now purely for REDCap EM framework registration — the standard stuff every EM needs:

- **`name`** — Module display name
- **`namespace`** — PHP namespace
- **`description`** — Shown in the EM manager
- **`framework-version`** — EM framework version (use 14+)
- **`authors`**, **`links`**, etc. — Standard EM metadata

No `api-actions`, no tool definitions. Clean separation of concerns.

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
- **Return raw arrays** — no HTTP wrapping. SecureChatAI handles serialization.
- **`"error" => true`** in the return array signals failure to the orchestrator
- **Log errors** with `$this->emError()`, debug info with `$this->emDebug()`

---

## `handleToolCall()` — The Router

This is the single entry point for all tool calls. SecureChatAI calls it directly via EM-to-EM PHP:

```php
// SecureChatAI does this internally:
$toolModule = \ExternalModules\ExternalModules::getModuleInstance($prefix);
$result = $toolModule->handleToolCall($action, $payload);
```

Your implementation is a simple switch:

```php
public function handleToolCall(string $action, array $payload = []): array
{
    switch ($action) {
        case "example_greet":              // ← matches "action" in tools.json
            return $this->toolGreet($payload);

        case "example_add":
            return $this->toolAdd($payload);

        default:
            return [
                "error" => true,
                "message" => "Unknown action: $action"
            ];
    }
}
```

**Key points:**
- **No HTTP surface** — this method is only callable from other EMs in the same PHP process
- **No payload normalization needed** — SecureChatAI always passes a clean PHP array
- **Returns raw arrays** — no HTTP status codes or headers to worry about
- **Action string** must exactly match the `"action"` field in `tools.json`

---

## Adding a New Tool — Checklist

Every time you add a tool, you touch exactly 2 places:

### ☐ 1. tools.json → add a tool definition

```json
{
    "name": "category.myAction",
    "description": "Clear description the LLM will read",
    "action": "my_action",
    "parameters": { ... },
    "readOnly": true,
    "destructive": false
}
```

### ☐ 2. PHP → switch case + tool method

```php
case "my_action":
    return $this->toolMyAction($payload);
```

**Verify the `action` string is identical in both places.**

---

## Testing Your Tools

### Option 1: End-to-End via SecureChatAI (Recommended)

The quickest way to test is through SecureChatAI itself — this is how tools actually run in production.

1. Enable SecureChatAI on a project
2. Add your tool EM's prefix to SecureChatAI's **Agent Tool EM Prefixes**
3. Call SecureChatAI's API with agent mode enabled:

```bash
curl -X POST https://your-redcap/api/ \
  -d "token=YOUR_SECURECHAT_PROJECT_TOKEN" \
  -d "content=externalModule" \
  -d "prefix=secure_chat_ai" \
  -d "action=callAI" \
  -d 'payload={"message":"Greet the name World formally","agent_mode":true}'
```

This exercises the full flow: SecureChatAI → LLM decides to use your tool → EM-to-EM call → response back to LLM → final answer.

### Option 2: PHP Unit / Integration Test

Since `handleToolCall()` is a plain PHP method, you can call it directly in any test harness:

```php
$toolEM = \ExternalModules\ExternalModules::getModuleInstance('redcap_agent_tool_template');
$result = $toolEM->handleToolCall('example_greet', ['name' => 'World', 'formal' => true]);
// $result = ["greeting" => "Good day, World. How may I assist you?", "formal" => true]
```

### Common Mistakes

| Symptom | Cause |
|---------|-------|
| Tool doesn't appear in LLM | Missing or malformed `tools.json`, or prefix not in SecureChatAI's prefix list |
| "Unknown action: X" | `action` string in tools.json doesn't match switch case in `handleToolCall()` |
| Module not found | EM not enabled system-wide, or prefix doesn't match directory name |
| LLM calls tool with wrong params | Check `parameters` schema in tools.json — LLMs follow it literally |

---

## File Structure

```
redcap_agent_yourtools_v9.9.9/
├── config.json                 ← EM registration (standard REDCap EM metadata)
├── tools.json                  ← Tool manifest (LLM tool definitions)
├── REDCapAgentYourTools.php    ← Router + tool implementations
├── emLoggerTrait.php           ← Logging helper (copy as-is, update namespace)
└── README.md
```

That's it. No views, no JS, no CSS. Tool EMs are backend-only.

---

## Reference: Complete Example Tool EM

See [`redcap_agent_record_tools`](https://github.com/susom/REDCapAgentRecordTools) for a production example with 8 tools covering project discovery, record CRUD, and survey link generation.
