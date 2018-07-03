<?php

include "./jules.php";

$data = <<<'EOD'
{
	"input": {
		"name": "Tim Davies",
		"email": "tim@example.com"
	},
	"request": {
		"method": "POST",
		"url": "/users",
		"type": "json",
		"data": {
			"name": "${ input.name }",
			"email": "${ input.email }"
		}
	}
}
EOD;

function json($data) {
	return json_encode($data, JSON_UNESCAPED_SLASHES);
}

$jules = new Jules();
echo(json($jules->readPath('input')));
echo(json($jules->readPath('input.name')));
echo(json_encode($jules->walk(json_decode($data))));
