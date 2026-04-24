<?php
namespace Stanford\REDCapAgentToolTemplate;

require_once "emLoggerTrait.php";

class REDCapAgentToolTemplate extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    public function __construct()
    {
        parent::__construct();
    }

    // =========================================================================
    //  TOOL ROUTER — handleToolCall()
    //
    //  Single entry point for all tool calls. Called by SecureChatAI via:
    //    getModuleInstance($prefix)->handleToolCall($action, $input)
    //
    //  This is an EM-to-EM direct PHP call — no HTTP, no API tokens.
    //  There is no external API surface; this method is only reachable
    //  from other EMs in the same PHP process.
    //
    //  $action  — matches the "action" field in tools.json (e.g., "example_greet")
    //  $payload — the parsed arguments from the LLM's tool call
    // =========================================================================
    public function handleToolCall(string $action, array $payload = []): array
    {
        $this->emDebug("Agent tool call", [
            'action' => $action,
            'payload' => $payload
        ]);

        // --- Route to tool method ---
        // Each case must match an "action" value in tools.json.
        // Each tool method receives $payload and returns an associative array.
        switch ($action) {

            case "example_greet":
                return $this->toolGreet($payload);

            case "example_add":
                return $this->toolAdd($payload);

            // Add your tools here:
            // case "my_action":
            //     return $this->toolMyAction($payload);

            default:
                return [
                    "error" => true,
                    "message" => "Unknown action: $action"
                ];
        }
    }

    // =========================================================================
    //  TOOL IMPLEMENTATIONS
    //  Each tool method follows this pattern:
    //    1. Validate required parameters (return error array if missing)
    //    2. Extract and type-cast parameters
    //    3. Do the work inside a try-catch
    //    4. Return a structured result array
    //    5. On failure: log with emError, return ["error" => true, "message" => "..."]
    // =========================================================================

    /**
     * example.greet — Return a greeting for the given name
     */
    public function toolGreet(array $payload)
    {
        // 1. Validate required parameters
        if (empty($payload['name'])) {
            return [
                "error" => true,
                "message" => "Missing required parameter: name"
            ];
        }

        // 2. Extract parameters
        $name = $payload['name'];
        $formal = $payload['formal'] ?? false;

        try {
            // 3. Do the work
            $greeting = $formal
                ? "Good day, $name. How may I assist you?"
                : "Hey $name!";

            // 4. Return structured result
            return [
                "greeting" => $greeting,
                "formal" => $formal
            ];
        } catch (\Exception $e) {
            // 5. Log and return error
            $this->emError("toolGreet error: " . $e->getMessage());
            return [
                "error" => true,
                "message" => "Failed to generate greeting: " . $e->getMessage()
            ];
        }
    }

    /**
     * example.add — Add two numbers
     */
    public function toolAdd(array $payload)
    {
        if (!isset($payload['a']) || !isset($payload['b'])) {
            return [
                "error" => true,
                "message" => "Missing required parameters: a and b"
            ];
        }

        $a = (float)$payload['a'];
        $b = (float)$payload['b'];

        try {
            return [
                "a" => $a,
                "b" => $b,
                "sum" => $a + $b
            ];
        } catch (\Exception $e) {
            $this->emError("toolAdd error: " . $e->getMessage());
            return [
                "error" => true,
                "message" => "Failed to add numbers: " . $e->getMessage()
            ];
        }
    }
}
