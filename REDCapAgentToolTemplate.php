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
    //  API ROUTER
    //  This is the single entry point for all agent tool calls.
    //  REDCap's EM framework calls this method when an API request arrives
    //  with content=externalModule and prefix=your_module_prefix.
    //
    //  $action  — matches a key in config.json's "api-actions" (e.g., "example_greet")
    //  $payload — the parsed request body / POST parameters
    // =========================================================================
    public function redcap_module_api($action = null, $payload = [])
    {
        // --- Normalize payload ---
        // REDCap API framework passes payload as ['payload' => '{"json":"string"}']
        if (!empty($payload['payload'])) {
            $payloadData = json_decode($payload['payload'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $payloadData;
            }
        } elseif (empty($payload)) {
            if (!empty($_POST['payload'])) {
                $payload = json_decode($_POST['payload'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->wrapResponse([
                        "error" => true,
                        "message" => "Invalid JSON in payload parameter"
                    ], 400);
                }
            } else {
                $raw = file_get_contents("php://input");
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload = $decoded;
                } else {
                    $payload = $_POST;
                }
            }
        }

        $this->emDebug("Agent API call", [
            'action' => $action,
            'payload' => $payload
        ]);

        // --- Route to tool method ---
        // Each case must match a key in config.json "api-actions".
        // Each tool method receives $payload and returns an associative array.
        switch ($action) {

            case "example_greet":
                return $this->wrapResponse(
                    $this->toolGreet($payload)
                );

            case "example_add":
                return $this->wrapResponse(
                    $this->toolAdd($payload)
                );

            // Add your tools here:
            // case "my_action":
            //     return $this->wrapResponse(
            //         $this->toolMyAction($payload)
            //     );

            default:
                return $this->wrapResponse([
                    "error" => true,
                    "message" => "Unknown action: $action"
                ], 400);
        }
    }

    // =========================================================================
    //  RESPONSE WRAPPER
    //  Converts a tool's result array into the HTTP response format REDCap expects.
    //  If the result contains "error" => true, the status code is set to 400.
    // =========================================================================
    private function wrapResponse(array $result, int $defaultStatus = 200)
    {
        return [
            "status"  => isset($result['error']) ? 400 : $defaultStatus,
            "body"    => json_encode($result),
            "headers" => ["Content-Type" => "application/json"]
        ];
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
