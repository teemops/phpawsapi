<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);

$app->add(new \Tuupola\Middleware\Cors([
    "origin" => "*",
    "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
    "headers.allow" => ["x-access-token", "content-type","Authorization", "If-Match", "If-Unmodified-Since"],
    "headers.expose" => ["Access-Control-Allow-Origin"],
    "credentials" => false,
    "cache" => 0,
    "error" => function ($request, $response, $arguments) {
        $data["status"] = "error";
        $data["message"] = $arguments["message"];
        return $response
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
]));






