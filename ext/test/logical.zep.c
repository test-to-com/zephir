
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
#include "kernel/array.h"
#include "kernel/operators.h"
#include "kernel/memory.h"
#include "kernel/fcall.h"


/**
 * Arithmetic operations
 */
ZEPHIR_INIT_CLASS(Test_Logical) {

	ZEPHIR_REGISTER_CLASS(Test, Logical, test, logical, test_logical_method_entry, 0);

	return SUCCESS;

}

PHP_METHOD(Test_Logical, testAnd1) {

	zend_bool _0;


	_0 = (0) ? 1 : 0;
	if (_0) {
		_0 = (0) ? 1 : 0;
	}
	RETURN_BOOL(_0);

}

PHP_METHOD(Test_Logical, testAnd2) {

	zend_bool _0;


	_0 = (0) ? 1 : 0;
	if (_0) {
		_0 = (1.5) ? 1 : 0;
	}
	RETURN_BOOL(_0);

}

PHP_METHOD(Test_Logical, testAnd3) {

	zend_bool _0, _1;


	_0 = (0) ? 1 : 0;
	if (_0) {
		_0 = (1.5) ? 1 : 0;
	}
	_1 = _0;
	if (_1) {
		_1 = ('A') ? 1 : 0;
	}
	RETURN_BOOL(_1);

}

PHP_METHOD(Test_Logical, testAnd4) {

	zend_bool _0, _1;


	_0 = (1) ? 1 : 0;
	if (_0) {
		_0 = (0) ? 1 : 0;
	}
	_1 = _0;
	if (_1) {
		_1 = 0;
	}
	RETURN_BOOL(_1);

}

PHP_METHOD(Test_Logical, testAnd9) {

	zend_bool _0;
	int b;
	zval *a, *b_param = NULL, *_1;

	zephir_fetch_params(0, 2, 0, &a, &b_param);

	b = zephir_get_intval(b_param);


	_0 = Z_TYPE_P(a) == IS_ARRAY;
	if (_0) {
		_0 = zephir_array_isset_long(a, b);
	}
	if (_0) {
		zephir_array_fetch_long(&_1, a, b, PH_NOISY | PH_READONLY, "test/logical.zep", 35 TSRMLS_CC);
		RETURN_CTORW(_1);
	}
	RETURN_NULL();

}

PHP_METHOD(Test_Logical, testOr1) {

	zend_bool _0;


	_0 = (0) ? 1 : 0;
	if (!(_0)) {
		_0 = (1) ? 1 : 0;
	}
	RETURN_BOOL(_0);

}

PHP_METHOD(Test_Logical, testOr2) {

	zend_bool _0;


	_0 = (0) ? 1 : 0;
	if (!(_0)) {
		_0 = 1;
	}
	RETURN_BOOL(_0);

}

PHP_METHOD(Test_Logical, testMixed1) {

	zend_bool _0, _1;
	int first, second;


	first = 0;
	second = 1;
	_0 = (first) ? 1 : 0;
	if (_0) {
		_0 = (second) ? 1 : 0;
	}
	_1 = _0;
	if (!(_1)) {
		_1 = (second) ? 1 : 0;
	}
	if (_1) {
		php_printf("%s", "ok");
	} else {
		php_printf("%s", "wrong");
	}

}

PHP_METHOD(Test_Logical, testMixed2) {

	zend_bool _1, _3;
	int ZEPHIR_LAST_CALL_STATUS;
	zval *match, *minLength, *_0 = NULL, *_2 = NULL, *_4, *_5 = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 2, 0, &match, &minLength);



	ZEPHIR_CALL_FUNCTION(&_0, "is_array", NULL, 68, match);
	zephir_check_call_status();
	_1 = zephir_is_true(_0);
	if (_1) {
		ZEPHIR_CALL_FUNCTION(&_2, "count", NULL, 3, match);
		zephir_check_call_status();
		_1 = ZEPHIR_IS_LONG(_2, 2);
	}
	_3 = _1;
	if (_3) {
		zephir_array_fetch_long(&_4, match, 0, PH_NOISY | PH_READONLY, "test/logical.zep", 65 TSRMLS_CC);
		ZEPHIR_CALL_FUNCTION(&_5, "strlen", NULL, 21, _4);
		zephir_check_call_status();
		_3 = ZEPHIR_GE(_5, minLength);
	}
	if (_3) {
		RETURN_MM_BOOL(1);
	}
	RETURN_MM_BOOL(0);

}

PHP_METHOD(Test_Logical, testMixed3) {

	zend_bool a, b, _0, _1, _2, _3;


	_0 = (0) ? 1 : 0;
	if (_0) {
		_0 = (1) ? 1 : 0;
	}
	_1 = _0;
	if (!(_1)) {
		_1 = (1) ? 1 : 0;
	}
	a = _1;
	_2 = (0) ? 1 : 0;
	if (_2) {
		_2 = (1) ? 1 : 0;
	}
	_3 = _2;
	if (!(_3)) {
		_3 = (1) ? 1 : 0;
	}
	b = _3;
	RETURN_BOOL(a == b);

}

PHP_METHOD(Test_Logical, testMixed4) {

	zval *a_param = NULL, *b_param = NULL;
	zend_bool a, b, _0, _1, _2;

	zephir_fetch_params(0, 2, 0, &a_param, &b_param);

	a = zephir_get_boolval(a_param);
	b = zephir_get_boolval(b_param);


	_0 = !a;
	if (_0) {
		_0 = !b;
	}
	_1 = _0;
	if (!(_1)) {
		_2 = a;
		if (_2) {
			_2 = b;
		}
		_1 = _2;
	}
	RETURN_BOOL(_1);

}

