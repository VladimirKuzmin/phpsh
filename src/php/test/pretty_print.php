<?php

// this is provided for our in-house unit testing and in case it's useful to
// anyone modifying the default pretty-printer
function ___phpsh___assert_eq(&$i, $f, $x, $y) {
    $f_of_x = $f($x);
    if ($y === $f_of_x) {
        $i++;
        return true;
    } else {
        error_log('Expected '.$f.'('.print_r($x, true).') to be '.
            print_r($y, true).', but instead got '.print_r($f_of_x, true));
        return false;
    }
}

function ___phpsh___assert_re(&$i, $f, $x, $re) {
    $f_of_x = $f($x);
    if (1 === preg_match($re, $f_of_x)) {
        $i++;
        return true;
    } else {
        error_log('Expected '.$f.'('.print_r($x, true).') to match re '.$re.
            ', but instead got '.print_r($f_of_x, true));
        return false;
    }
}

function ___phpsh___pretty_print_test() {
    $i = 0;
    assert(___phpsh___assert_eq($i, '___phpsh___pretty_print',
        null,
        'null'
    ));
    assert(___phpsh___assert_eq($i, '___phpsh___pretty_print',
        true,
        'true'
    ));
    assert(___phpsh___assert_eq($i, '___phpsh___pretty_print',
        false,
        'false'
    ));
    assert(___phpsh___assert_eq($i, '___phpsh___pretty_print',
        4,
        '4'
    ));
    assert(___phpsh___assert_eq($i, '___phpsh___pretty_print',
        3.14,
        '3.14'
    ));
    assert(___phpsh___assert_eq($i, '___phpsh___pretty_print',
        "A\\\"'\'B\nC",
        '"A\\\\\"\'\\\\\'B\\nC"'
    ));
    assert(___phpsh___assert_re($i, '___phpsh___pretty_print',
        fopen('phpshtest.deleteme', 'w'),
        '<resource #\d+ of type stream>'
    ));
    assert(___phpsh___assert_eq($i, '___phpsh___pretty_print',
        array(04 => 'lol', '04' => 'lolo'),
        "array(\n  4 => \"lol\",\n  \"04\" => \"lolo\",\n)"
    ));
    $arr = array();
    $arr['self'] = $arr;
    // note the manifested depth might actually be variable and unknowable.
    // so we may have to loosen this test..
    assert(___phpsh___assert_eq($i, '___phpsh___pretty_print', $arr,
        "array(\n  \"self\" => array(\n    \"self\" => *RECURSION*,\n  ),\n)"
    ));
    $arr = array();
    $arr['fake'] = "Array\n *RECURSION*";
    $arr['sref'] = &$arr;
    $arr['self'] = $arr;
    assert(___phpsh___assert_eq($i, '___phpsh___pretty_print', $arr,
        "array(\n  \"fake\" => \"Array\\n *RECURSION*\",\n  \"sref\" => &array(\n    \"fake\" => \"Array\\n *RECURSION*\",\n    \"sref\" => &array(\n      \"fake\" => \"Array\\n *RECURSION*\",\n      \"sref\" => *RECURSION*,\n      \"self\" => array(\n        \"fake\" => \"Array\\n *RECURSION*\",\n        \"sref\" => *RECURSION*,\n        \"self\" => array(\n          \"fake\" => \"Array\\n *RECURSION*\",\n          \"sref\" => *RECURSION*,\n          \"self\" => *RECURSION*,\n        ),\n      ),\n    ),\n    \"self\" => array(\n      \"fake\" => \"Array\\n *RECURSION*\",\n      \"sref\" => &array(\n        \"fake\" => \"Array\\n *RECURSION*\",\n        \"sref\" => *RECURSION*,\n        \"self\" => array(\n          \"fake\" => \"Array\\n *RECURSION*\",\n          \"sref\" => *RECURSION*,\n          \"self\" => *RECURSION*,\n        ),\n      ),\n      \"self\" => array(\n        \"fake\" => \"Array\\n *RECURSION*\",\n        \"sref\" => &array(\n          \"fake\" => \"Array\\n *RECURSION*\",\n          \"sref\" => *RECURSION*,\n          \"self\" => *RECURSION*,\n        ),\n        \"self\" => *RECURSION*,\n      ),\n    ),\n  ),\n  \"self\" => array(\n    \"fake\" => \"Array\\n *RECURSION*\",\n    \"sref\" => &array(\n      \"fake\" => \"Array\\n *RECURSION*\",\n      \"sref\" => &array(\n        \"fake\" => \"Array\\n *RECURSION*\",\n        \"sref\" => *RECURSION*,\n        \"self\" => array(\n          \"fake\" => \"Array\\n *RECURSION*\",\n          \"sref\" => *RECURSION*,\n          \"self\" => *RECURSION*,\n        ),\n      ),\n      \"self\" => array(\n        \"fake\" => \"Array\\n *RECURSION*\",\n        \"sref\" => &array(\n          \"fake\" => \"Array\\n *RECURSION*\",\n          \"sref\" => *RECURSION*,\n          \"self\" => *RECURSION*,\n        ),\n        \"self\" => *RECURSION*,\n      ),\n    ),\n    \"self\" => array(\n      \"fake\" => \"Array\\n *RECURSION*\",\n      \"sref\" => &array(\n        \"fake\" => \"Array\\n *RECURSION*\",\n        \"sref\" => &array(\n          \"fake\" => \"Array\\n *RECURSION*\",\n          \"sref\" => *RECURSION*,\n          \"self\" => *RECURSION*,\n        ),\n        \"self\" => *RECURSION*,\n      ),\n      \"self\" => *RECURSION*,\n    ),\n  ),\n)"
    ));
    assert(___phpsh___assert_eq($i, '___phpsh___pretty_print',
        array('a[b]c' => 4),
        "array(\n  \"a[b]c\" => 4,\n)"
    ));
    $var_to_ref = 4;
    assert(___phpsh___assert_eq($i, '___phpsh___pretty_print',
        array('hi' => &$var_to_ref),
        "array(\n  \"hi\" => &4,\n)"
    ));
    return $i;
}
