
#ifdef HAVE_CONFIG_H
#include "../ext_config.h"
#endif

#include <php.h>
#include "../php_ext.h"
#include "../ext.h"

#include <Zend/zend_operators.h>
#include <Zend/zend_exceptions.h>
#include <Zend/zend_interfaces.h>

#include "kernel/main.h"
#include "kernel/memory.h"
#include "kernel/array.h"
#include "kernel/fcall.h"


ZEPHIR_INIT_CLASS(Test_Json) {

	ZEPHIR_REGISTER_CLASS(Test, Json, test, json, test_json_method_entry, 0);

	return SUCCESS;

}

PHP_METHOD(Test_Json, testEncodeObject) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *obj;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(obj);
	zephir_create_array(obj, 3, 0 TSRMLS_CC);
	add_assoc_stringl_ex(obj, SS("a"), SL("hello"), 1);
	add_assoc_stringl_ex(obj, SS("b"), SL("world"), 1);
	add_assoc_long_ex(obj, SS("c"), 128);
	ZEPHIR_RETURN_CALL_FUNCTION("json_encode", NULL, 67, obj);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Json, testEncodeArray) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *arr, *_0 = NULL;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(arr);
	zephir_create_array(arr, 3, 0 TSRMLS_CC);
	ZEPHIR_INIT_VAR(_0);
	ZVAL_LONG(_0, 1);
	zephir_array_fast_append(arr, _0);
	ZEPHIR_INIT_NVAR(_0);
	ZVAL_LONG(_0, 2);
	zephir_array_fast_append(arr, _0);
	ZEPHIR_INIT_NVAR(_0);
	ZVAL_LONG(_0, 3);
	zephir_array_fast_append(arr, _0);
	ZEPHIR_RETURN_CALL_FUNCTION("json_encode", NULL, 67, arr);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Json, testEncodeOptions) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *arr, *_0 = NULL, _1;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(arr);
	zephir_create_array(arr, 4, 0 TSRMLS_CC);
	ZEPHIR_INIT_VAR(_0);
	ZVAL_STRING(_0, "<foo>", 1);
	zephir_array_fast_append(arr, _0);
	ZEPHIR_INIT_NVAR(_0);
	ZVAL_STRING(_0, "'bar'", 1);
	zephir_array_fast_append(arr, _0);
	ZEPHIR_INIT_NVAR(_0);
	ZVAL_STRING(_0, "&blong&", 1);
	zephir_array_fast_append(arr, _0);
	ZEPHIR_INIT_NVAR(_0);
	ZVAL_STRING(_0, "\xc3\xa9", 1);
	zephir_array_fast_append(arr, _0);
	ZEPHIR_SINIT_VAR(_1);
	ZVAL_LONG(&_1, 1);
	ZEPHIR_RETURN_CALL_FUNCTION("json_encode", NULL, 67, arr, &_1);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Json, testDecodeObject) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *obj;

	ZEPHIR_MM_GROW();
	ZEPHIR_INIT_VAR(obj);
	ZVAL_STRING(obj, "{\"a\":\"hello\",\"b\":\"world\",\"c\":128}", 1);

	ZEPHIR_RETURN_CALL_FUNCTION("json_decode", NULL, 34, obj);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Json, testDecodeObject2) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *obj;

	ZEPHIR_MM_GROW();
	ZEPHIR_INIT_VAR(obj);
	ZVAL_STRING(obj, "{\"a\":\"hello\",\"b\":\"world\",\"c\":128}", 1);

	ZEPHIR_RETURN_CALL_FUNCTION("json_decode", NULL, 34, obj, ZEPHIR_GLOBAL(global_true));
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Json, testDecodeArray) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *arr;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(arr);
	ZVAL_STRING(arr, "[1,2,3]", 1);
	ZEPHIR_RETURN_CALL_FUNCTION("json_decode", NULL, 34, arr);
	zephir_check_call_status();
	RETURN_MM();

}

