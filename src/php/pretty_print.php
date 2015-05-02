<?php

namespace __phpsh__;

class Exception extends \Exception {}

function pretty_print($x) {
    return _parse_dump($x, _var_dump_cap($x));
}

function _var_dump_cap($x) {
    ob_start();
    var_dump($x);
    $str = ob_get_contents();
    ob_end_clean();
    return rtrim($str);
}

function _str_lit($str) {
    static $str_lit_esc_chars =
        "\\\"\000\001\002\003\004\005\006\007\010\011\012\013\014\015\016\017\020\021\022\023\024\025\026\027\030\031\032\033\034\035\036\037";
    // todo?: addcslashes makes weird control chars in octal instead of hex.
    //        is hex kwlr in general?  if so might want our own escaper here
    return '"'.addcslashes($str, $str_lit_esc_chars).'"';
}

function _parse_dump($x, $dump, &$pos=0, $normal_end_check=true, $_depth=0) {
    static $indent_str = '  ';
    $depth_str = str_repeat($indent_str, $_depth);
    // ad hoc parsing not very fun.. use lemon or something? or is that overkill
    switch ($dump[$pos]) {
        case 'N':
            _parse_dump_assert($dump, $pos, 'NULL');
            return 'null';
        case '&':
            $pos++;
            return '&' . _parse_dump(
                $x, $dump, $pos, $normal_end_check,$_depth);
        case 'a':
            _parse_dump_assert($dump, $pos, 'array');
            $arr_len = (int)_parse_dump_delim_grab($dump, $pos, false);
            _parse_dump_assert($dump, $pos, " {\n");
            $arr_lines = _parse_dump_arr_lines(
                $x, $dump, $pos, $arr_len, $_depth, $depth_str, $indent_str);
            _parse_dump_assert(
                $dump, $pos, $depth_str."}", $normal_end_check);
            return implode("\n", array_merge(['array('], $arr_lines, [$depth_str.')']));
        case 'o':
            _parse_dump_assert($dump, $pos, 'object');
            $obj_type_str = _parse_dump_delim_grab($dump, $pos);
            $obj_num_str = _parse_dump_delim_grab($dump, $pos, false, '# ');
            $obj_len = (int)_parse_dump_delim_grab($dump, $pos);
            _parse_dump_assert($dump, $pos, " {\n");
            $obj_lines = _parse_dump_obj_lines(
                $x, $dump, $pos, $obj_len, $_depth, $depth_str, $indent_str);
            _parse_dump_assert(
                $dump, $pos, $depth_str.'}', $normal_end_check);
            return implode("\n", array_merge(
                ["<object #{$obj_num_str} of type {$obj_type_str}> \{"],
                $obj_lines,
                [$depth_str.'}']
            ));
        case 'b':
            _parse_dump_assert($dump, $pos, 'bool(');
            switch ($dump[$pos]) {
                case 'f':
                    _parse_dump_assert($dump, $pos, 'false)', $normal_end_check);
                    return 'false';
                case 't':
                    _parse_dump_assert($dump, $pos, 'true)', $normal_end_check);
                    return 'true';
            }
        case 'f':
            _parse_dump_assert($dump, $pos, 'float');
            return _parse_dump_delim_grab($dump, $pos, $normal_end_check);
        case 'd':
            _parse_dump_assert($dump, $pos, 'double');
            return _parse_dump_delim_grab($dump, $pos, $normal_end_check);
        case 'i':
            _parse_dump_assert($dump, $pos, 'int');
            return _parse_dump_delim_grab($dump, $pos, $normal_end_check);
        case 'r':
            _parse_dump_assert($dump, $pos, 'resource');
            $rsrc_num_str = _parse_dump_delim_grab($dump, $pos);
            _parse_dump_assert($dump, $pos, ' of type ');
            $rsrc_type_str =
                _parse_dump_delim_grab($dump, $pos, $normal_end_check);
            return '<resource #'.$rsrc_num_str.' of type '.$rsrc_type_str.'>';
        case 's':
            _parse_dump_assert($dump, $pos, 'string');
            $str_len = (int)_parse_dump_delim_grab($dump, $pos);
            _parse_dump_assert($dump, $pos, ' "');
            $str = substr($dump, $pos, $str_len);
            $pos += $str_len;
            _parse_dump_assert($dump, $pos, '"', $normal_end_check);
            return _str_lit($str);
        default:
            if (ini_get('xdebug.cli_color') == '2') {
                echo $dump;
            } else {
                throw new Exception(
                    "parse error unrecognized type at position {$pos}: ".substr($dump, $pos));
            }
    }
}

function _parse_dump_arr_lines($x, $dump, &$pos, $arr_len, $depth, $depth_str, $indent_str) {
    $arr_lines = [];
    foreach (array_keys($x) as $key) {
        if (is_int($key)) {
            $key_str_php = (string)$key;
            $key_str_correct = $key_str_php;
        } else {
            $key_str_php = '"'.$key.'"';
            $key_str_correct = _str_lit($key);
        }
        _parse_dump_assert(
            $dump, $pos,
            "{$depth_str}{$indent_str}[{$key_str_php}]=>\n{$depth_str}{$indent_str}");
        if ($dump[$pos] == '*') {
            _parse_dump_assert($dump, $pos, '*RECURSION*');
            $val = '*RECURSION*';
        } else {
            $val = _parse_dump($x[$key], $dump, $pos, false, $depth + 1);
        }
        _parse_dump_assert($dump, $pos, "\n");
        $arr_lines[] = $depth_str.$indent_str.$key_str_correct.' => '.$val.',';
    }
    return $arr_lines;
}

function _parse_dump_obj_lines($x, $dump, &$pos, $arr_len, $depth, $depth_str, $indent_str) {
    $arr_lines = array();
    // this exposes private/protected members (a hack within a hack)
    $x_arr = _obj_to_arr($x);
    for ($i = 0; $i < $arr_len; $i++) {
        _parse_dump_assert($dump, $pos, $depth_str.$indent_str.'[');
        $key = _parse_dump_delim_grab($dump, $pos, false, '""');
        if ($dump[$pos] == ':') {
            $key .= ':'._parse_dump_delim_grab($dump, $pos, false, ':]');
            $pos--;
        }
        _parse_dump_assert($dump, $pos, "]=>\n".$depth_str.$indent_str);
        if ($dump[$pos] == '*') {
            _parse_dump_assert($dump, $pos, '*RECURSION*');
            $val = '*RECURSION*';
        } else {
            $colon_pos = strpos($key, ':');
            if ($colon_pos === false) {
                $key_unannotated = $key;
            } else {
                $key_unannotated = substr($key, 0, $colon_pos);
            }
            $val = _parse_dump($x_arr[$key_unannotated], $dump, $pos,
                false, $depth + 1);
        }
        _parse_dump_assert($dump, $pos, "\n");
        $arr_lines[] = $depth_str.$indent_str.$key.' => '.$val.',';
    }
    return $arr_lines;
}

function _obj_to_arr($x) {
    if (is_object($x)) {
        $raw_array = (array)$x;
        $result = array();
        foreach ($raw_array as $key => $value) {
            $key = preg_replace('/\\000.*\\000/', '', $key);
            $result[$key] = $value;
        }
        return $result;
    }
    return (array)$x;
}

function _parse_dump_assert($dump, &$pos, $str, $end=false) {
    $len = strlen($str);
    if ($str !== '' && substr($dump, $pos, $len) !== $str) {
        throw new Exception(
            "parse error looking for '{$str}' at position {$pos}; found instead: "
            .substr($dump, $pos));
    }
    $pos += $len;
    if ($end && strlen($dump) > $pos) {
        throw new Exception('parse error unexpected input after position '.$pos);
    }
    return true;
}

function _parse_dump_delim_grab($dump, &$pos, $end=false, $delims='()') {
    assert(strlen($delims) === 2);
    $pos_open_paren = $pos;
    _parse_dump_assert($dump, $pos, $delims[0]);
    $pos_close_paren = strpos($dump, $delims[1], $pos_open_paren + 1);
    if ($pos_close_paren === false) {
        throw new Exception(
            "parse error expecting '{$delims[1]}' after position {$pos}");
    }
    $pos = $pos_close_paren + 1;
    if ($end) {
        _parse_dump_assert($dump, $pos, '', true);
    }
    return substr($dump, $pos_open_paren + 1, $pos_close_paren - $pos_open_paren - 1);
}
