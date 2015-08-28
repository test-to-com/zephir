
#ifdef HAVE_CONFIG_H
#include "../../ext_config.h"
#endif

#include <php.h>
#include "../../php_ext.h"
#include "../../ext.h"

#include <Zend/zend_operators.h>
#include <Zend/zend_exceptions.h>
#include <Zend/zend_interfaces.h>

#include "kernel/main.h"
#include "kernel/memory.h"
#include "kernel/fcall.h"
#include "kernel/concat.h"
#include "kernel/operators.h"


ZEPHIR_INIT_CLASS(Test_BuiltIn_StringMethods) {

	ZEPHIR_REGISTER_CLASS(Test\\BuiltIn, StringMethods, test, builtin_stringmethods, test_builtin_stringmethods_method_entry, 0);

	return SUCCESS;

}

PHP_METHOD(Test_BuiltIn_StringMethods, getLength1) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval _0, *_1 = NULL;

	ZEPHIR_MM_GROW();

	ZEPHIR_SINIT_VAR(_0);
	ZVAL_STRING(&_0, "hello", 0);
	ZEPHIR_CALL_FUNCTION(&_1, "strlen", NULL, 21, &_0);
	zephir_check_call_status();
	RETURN_CCTOR(_1);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getLength2) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval _0, *_1 = NULL;

	ZEPHIR_MM_GROW();

	ZEPHIR_SINIT_VAR(_0);
	ZVAL_STRING(&_0, "hello", 0);
	ZEPHIR_CALL_FUNCTION(&_1, "strlen", NULL, 21, &_0);
	zephir_check_call_status();
	RETURN_CCTOR(_1);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getLength3) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *_1 = NULL;
	zval *_0;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(_0);
	ZEPHIR_CONCAT_SS(_0, "hello", "hello");
	ZEPHIR_CALL_FUNCTION(&_1, "strlen", NULL, 21, _0);
	zephir_check_call_status();
	RETURN_CCTOR(_1);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getLength4) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *a_param = NULL, *_0 = NULL;
	zval *a = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &a_param);

	zephir_get_strval(a, a_param);


	ZEPHIR_CALL_FUNCTION(&_0, "strlen", NULL, 21, a);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getLength5) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *a_param = NULL, *_1 = NULL;
	zval *a = NULL, *_0;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &a_param);

	zephir_get_strval(a, a_param);


	ZEPHIR_INIT_VAR(_0);
	ZEPHIR_CONCAT_SV(_0, "hello", a);
	ZEPHIR_CALL_FUNCTION(&_1, "strlen", NULL, 21, _0);
	zephir_check_call_status();
	RETURN_CCTOR(_1);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getIndex) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *needle_param = NULL, *_0 = NULL;
	zval *str = NULL, *needle = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 2, 0, &str_param, &needle_param);

	zephir_get_strval(str, str_param);
	zephir_get_strval(needle, needle_param);


	ZEPHIR_CALL_FUNCTION(&_0, "strpos", NULL, 22, str, needle);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getIndexWithPosition) {

	int position, ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *needle_param = NULL, *position_param = NULL, _0, *_1 = NULL;
	zval *str = NULL, *needle = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 3, 0, &str_param, &needle_param, &position_param);

	zephir_get_strval(str, str_param);
	zephir_get_strval(needle, needle_param);
	position = zephir_get_intval(position_param);


	ZEPHIR_SINIT_VAR(_0);
	ZVAL_LONG(&_0, position);
	ZEPHIR_CALL_FUNCTION(&_1, "strpos", NULL, 22, str, needle, &_0);
	zephir_check_call_status();
	RETURN_CCTOR(_1);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getTrimmed) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval _0, *_1 = NULL;

	ZEPHIR_MM_GROW();

	ZEPHIR_SINIT_VAR(_0);
	ZVAL_STRING(&_0, " hello \t\n", 0);
	ZEPHIR_CALL_FUNCTION(&_1, "trim", NULL, 23, &_0);
	zephir_check_call_status();
	RETURN_CCTOR(_1);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getTrimmed1) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "trim", NULL, 23, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getLeftTrimmed) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "ltrim", NULL, 24, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getRightTrimmed) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "rtrim", NULL, 25, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getLower) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "strtolower", NULL, 26, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getUpper) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "strtoupper", NULL, 27, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getLowerFirst) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "lcfirst", NULL, 28, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getUpperFirst) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "ucfirst", NULL, 29, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getFormatted) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, _0, *_1 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_SINIT_VAR(_0);
	ZVAL_STRING(&_0, "hello %s!", 0);
	ZEPHIR_CALL_FUNCTION(&_1, "sprintf", NULL, 7, &_0, str);
	zephir_check_call_status();
	RETURN_CCTOR(_1);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getMd5) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "md5", NULL, 30, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getSha1) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "sha1", NULL, 31, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getNl2br) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "nl2br", NULL, 32, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getParsedCsv) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "str_getcsv", NULL, 33, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getParsedJson) {

	int ZEPHIR_LAST_CALL_STATUS;
	zend_bool assoc;
	zval *str_param = NULL, *assoc_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 1, &str_param, &assoc_param);

	zephir_get_strval(str, str_param);
	if (!assoc_param) {
		assoc = 1;
	} else {
		assoc = zephir_get_boolval(assoc_param);
	}


	ZEPHIR_CALL_FUNCTION(&_0, "json_decode", NULL, 34, str, str, (assoc ? ZEPHIR_GLOBAL(global_true) : ZEPHIR_GLOBAL(global_false)));
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getRepeatted) {

	int count, ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *count_param = NULL, _0, *_1 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 2, 0, &str_param, &count_param);

	zephir_get_strval(str, str_param);
	count = zephir_get_intval(count_param);


	ZEPHIR_SINIT_VAR(_0);
	ZVAL_LONG(&_0, count);
	ZEPHIR_CALL_FUNCTION(&_1, "str_repeat", NULL, 35, str, &_0);
	zephir_check_call_status();
	RETURN_CCTOR(_1);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getShuffled) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "str_shuffle", NULL, 36, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getSplited) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *del_param = NULL, *_0 = NULL;
	zval *str = NULL, *del = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 2, 0, &str_param, &del_param);

	zephir_get_strval(str, str_param);
	zephir_get_strval(del, del_param);


	ZEPHIR_CALL_FUNCTION(&_0, "str_split", NULL, 37, str, del);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getCompare) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *left_param = NULL, *right_param = NULL, *_0 = NULL;
	zval *left = NULL, *right = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 2, 0, &left_param, &right_param);

	zephir_get_strval(left, left_param);
	zephir_get_strval(right, right_param);


	ZEPHIR_CALL_FUNCTION(&_0, "strcmp", NULL, 38, left, right);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getCompareLocale) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *left_param = NULL, *right_param = NULL, *_0 = NULL;
	zval *left = NULL, *right = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 2, 0, &left_param, &right_param);

	zephir_get_strval(left, left_param);
	zephir_get_strval(right, right_param);


	ZEPHIR_CALL_FUNCTION(&_0, "strcoll", NULL, 39, left, right);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getReversed) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "strrev", NULL, 40, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_BuiltIn_StringMethods, getHtmlSpecialChars) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str_param = NULL, *_0 = NULL;
	zval *str = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &str_param);

	zephir_get_strval(str, str_param);


	ZEPHIR_CALL_FUNCTION(&_0, "htmlspecialchars", NULL, 41, str);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

