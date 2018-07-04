<?php

namespace Jules;

class Jules {
	const PATH_START_PATTERN = '#([a-zA-Z$_][a-zA-Z0-9$_]*)#A';
	const PATH_PATTERN = '#(?|\.([a-zA-Z$_][a-zA-Z0-9$_]*)|\[\s*("(?:\\\\[\s\S]|[^"\\\\])*"|\'(?:\\\\[\s\S]|[^\'\\\\])*\'|\d+)\s*\])#A';
	const EMBEDDED_EXPR_PATTERN = '#{\s*[\'"]\$#';
	const EMBEDDED_PATTERN = '#"(?:\\\\[\s\S]|[^"\\\\])*"|\'(?:\\\\[\s\S]|[^\'\\\\])*\'|{|}|\[|\]#';
	const MAX_FOLLOW_REF = 50;

	public function construct() {
	}

	public function readPath($str) {
		$path = [];
		$match = preg_match(self::PATH_START_PATTERN, $str, $matches);
		if ($match) {
			if ($matches[1] != '$') {
				array_push($path, $matches[1]);
			}
			$path_str = $matches[0];
			while ($match = preg_match(self::PATH_PATTERN, $str, $matches, 0, strlen($path_str))) {
				if (strncmp($matches[0], '.', 1) == 0) {
					array_push($path, $matches[1]);
					$path_str .= $matches[0];
				} else if (strncmp($matches[1], '"', 1) == 0) {
					array_push($path, json_decode($matches[1]));
					$path_str .= $matches[0];
				} else if (strncmp($matches[1], "'", 1) == 0) {
					$between_quotes = substr($matches[1], 1, strlen($matches[1]) - 2);
					$between_quotes_escaped = preg_replace("#(?<!\\\\)\"#", "\\\"", $between_quotes);
					$between_quotes_escaped_unescaped = preg_replace("#\\\\'#", "'", $between_quotes_escaped);
					$json_str = '"' . $between_quotes_escaped_unescaped . '"';
					$decoded_json_str = json_decode($json_str);
					array_push($path, $decoded_json_str);
					$path_str .= $matches[0];
				} else {
					array_push($path, $matches[1]);
					$path_str .= $matches[0];
				}
			}
			return [$path, $path_str];
		}
	}

	public function fnGet($data, $path, $follows = 0) {
		$pathResult = $this->readPath($path);
		if ($pathResult[1] != $path) {
			return;
		}
		$pathArr = $pathResult[0];
		if ($follows >= self::MAX_FOLLOW_REF) {
			return;
		}
		$ref = $data;
		foreach ($pathArr as $key => $value) {
			if ($ref instanceof \stdClass && isset($ref->{$value})) {
				$ref = $ref->{$value};
			} else {
				return;
			}
		}
		if ($ref instanceof \stdClass && isset($ref->{'$get'})) {
			return $this->fnGet($data, $ref->{'$get'}, $follows + 1);
		}
		return $ref;
	}

	public function singleQuoteToJson($str) {
		$between_quotes = substr($str, 1, strlen($str) - 2);
		$between_quotes_escaped = preg_replace("#(?<!\\\\)\"#", "\\\"", $between_quotes);
		$between_quotes_escaped_unescaped = preg_replace("#\\\\'#", "'", $between_quotes_escaped);
		return '"' . $between_quotes_escaped_unescaped . '"';
	}

	public function evalStr($str, $root) {
		$result = '';
		$offset = 0;
		while ($match = preg_match(self::EMBEDDED_EXPR_PATTERN, $str, $matches, PREG_OFFSET_CAPTURE, $offset)) {
			$result .= substr($str, $offset, $matches[0][1] - $offset);
			$offset = $matches[0][1] + 1;
			$brackets = ['{'];
			$json = '{';
			while (count($brackets) > 0) {
				$match = preg_match(self::EMBEDDED_PATTERN, $str, $matches, PREG_OFFSET_CAPTURE, $offset);
				if (!$match) {
					return $str;
				}

				$match_str = $matches[0][0];
				$match_offset = $matches[0][1];

				$json .= substr($str, $offset, $match_offset - $offset);
				$firstChar = substr($match_str, 0, 1);
				if ($firstChar == "'") {
					$json .= $this->singleQuoteToJson($match_str);
				} else {
					$json .= $match_str;
				}

				if ($match_str == '{' || $match_str == '[') {
					array_push($brackets, $match_str);
				} else if ($match_str == '}' || $match_str == ']') {
					$open_bracket = array_pop($brackets);
					if (!($open_bracket == '{' && $match_str == '}' || $open_bracket == '[' && $match_str == ']')) {
						return $str;
					}
				}
				$offset = $match_offset + strlen($match_str);
			}
			$tag = json_decode($json);
			$tag_output = '';
			if (isset($tag->{'$get'})) {
				$tag_output = $this->fnGet($root, $tag->{'$get'});
			}
			$result .= $tag_output;
		}
		$result .= substr($str, $offset);
		return $result;
	}

	public function eval($data, $root = NULL) {
		if ($root == NULL) {
			$root = $data;
			$data = json_decode(json_encode($data), FALSE);
		}

		if ($data instanceof \stdClass) {
			foreach ($data as $key => $value) {
				if ($value instanceof \stdClass && isset($value->{'$get'})) {
					$data->{$key} = $this->fnGet($root, $value->{'$get'});
				} else if ($value instanceof \stdClass) {
					$this->eval($value, $root);
				} else if (is_string($value)) {
					if (preg_match(self::EMBEDDED_EXPR_PATTERN, $value)) {
						$data->{$key} = $this->evalStr($value, $root);
					}
				}
			}
		}
		return $data;
	}
}
