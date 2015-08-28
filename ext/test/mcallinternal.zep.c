
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
#include "kernel/fcall.h"
#include "kernel/operators.h"


/**
 * Method calls
 */
ZEPHIR_INIT_CLASS(Test_McallInternal) {

	ZEPHIR_REGISTER_CLASS(Test, McallInternal, test, mcallinternal, test_mcallinternal_method_entry, 0);

	return SUCCESS;

}

static void zep_Test_McallInternal_a(int ht, zval *return_value, zval **return_value_ptr, zval *this_ptr, int return_value_used TSRMLS_DC) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval _0;

	ZEPHIR_MM_GROW();

	ZEPHIR_SINIT_VAR(_0);
	ZVAL_STRING(&_0, "hello", 0);
	ZEPHIR_RETURN_CALL_FUNCTION("strlen", NULL, 21, &_0);
	zephir_check_call_status();
	RETURN_MM();

}

static void zep_Test_McallInternal_b(int ht, zval *return_value, zval **return_value_ptr, zval *this_ptr, int return_value_used, zval *a_ext, zval *b_ext TSRMLS_DC) {

	zval *a, *b;

	a = a_ext;
	b = b_ext;




}

static void zep_Test_McallInternal_c(int ht, zval *return_value, zval **return_value_ptr, zval *this_ptr, int return_value_used, zval *a_param_ext, zval *b_param_ext TSRMLS_DC) {

	zval *a_param = NULL, *b_param = NULL;
	long a, b;

	a_param = a_param_ext;
	b_param = b_param_ext;

	a = zephir_get_intval(a_param);
	b = zephir_get_intval(b_param);


	RETURN_LONG((a + b));

}

PHP_METHOD(Test_McallInternal, e) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval _0;

	ZEPHIR_MM_GROW();

	ZEPHIR_SINIT_VAR(_0);
	ZVAL_STRING(&_0, "hello", 0);
	ZEPHIR_RETURN_CALL_FUNCTION("strlen", NULL, 21, &_0);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_McallInternal, d) {

	zval *_3 = NULL;
	zend_bool _0;
	int a = 0, i, _1, _2, ZEPHIR_LAST_CALL_STATUS;

	ZEPHIR_MM_GROW();

	_2 = 1000000;
	_1 = 0;
	_0 = 0;
	if (_1 <= _2) {
		while (1) {
			if (_0) {
				_1++;
				if (!(_1 <= _2)) {
					break;
				}
			} else {
				_0 = 1;
			}
			i = _1;
			ZEPHIR_CALL_INTERNAL_METHOD_P0(&_3, this_ptr, zep_Test_McallInternal_a);
			zephir_check_call_status();
			a += zephir_get_intval(_3);
		}
	}
	RETURN_MM_LONG(a);

}

PHP_METHOD(Test_McallInternal, f) {

	zephir_fcall_cache_entry *_4 = NULL;
	zval *_3 = NULL;
	zend_bool _0;
	int a = 0, i, _1, _2, ZEPHIR_LAST_CALL_STATUS;

	ZEPHIR_MM_GROW();

	_2 = 1000000;
	_1 = 0;
	_0 = 0;
	if (_1 <= _2) {
		while (1) {
			if (_0) {
				_1++;
				if (!(_1 <= _2)) {
					break;
				}
			} else {
				_0 = 1;
			}
			i = _1;
			ZEPHIR_CALL_METHOD(&_3, this_ptr, "e", &_4, 0);
			zephir_check_call_status();
			a += zephir_get_intval(_3);
		}
	}
	RETURN_MM_LONG(a);

}

PHP_METHOD(Test_McallInternal, g) {

	zval *_3 = NULL, _4 = zval_used_for_init, _5 = zval_used_for_init;
	zend_bool _0;
	long i;
	int a = 0, _1, _2, ZEPHIR_LAST_CALL_STATUS;

	ZEPHIR_MM_GROW();

	_2 = 1000;
	_1 = 0;
	_0 = 0;
	if (_1 <= _2) {
		while (1) {
			if (_0) {
				_1++;
				if (!(_1 <= _2)) {
					break;
				}
			} else {
				_0 = 1;
			}
			i = _1;
			ZEPHIR_SINIT_NVAR(_4);
			ZVAL_LONG(&_4, i);
			ZEPHIR_SINIT_NVAR(_5);
			ZVAL_LONG(&_5, i);
			ZEPHIR_CALL_INTERNAL_METHOD_P2(&_3, this_ptr, zep_Test_McallInternal_c, &_4, &_5);
			zephir_check_call_status();
			a += zephir_get_intval(_3);
		}
	}
	RETURN_MM_LONG(a);

}

static void zep_Test_McallInternal_other(int ht, zval *return_value, zval **return_value_ptr, zval *this_ptr, int return_value_used, zval *a_param_ext, zval *b_param_ext TSRMLS_DC) {

	zval *a_param = NULL, *b_param = NULL;
	long a, b;

	a_param = a_param_ext;
	b_param = b_param_ext;

	a = zephir_get_intval(a_param);
	b = zephir_get_intval(b_param);


	RETURN_DOUBLE(zephir_safe_div_long_long(a, b TSRMLS_CC));

}

PHP_METHOD(Test_McallInternal, callFibonacci) {

	zval *_3 = NULL, _4 = zval_used_for_init, _5 = zval_used_for_init;
	int _1, _2, ZEPHIR_LAST_CALL_STATUS;
	zend_bool _0;
	long i = 0;
	double p = 0;

	ZEPHIR_MM_GROW();

	_2 = 10000000;
	_1 = 0;
	_0 = 0;
	if (_1 <= _2) {
		while (1) {
			if (_0) {
				_1++;
				if (!(_1 <= _2)) {
					break;
				}
			} else {
				_0 = 1;
			}
			i = _1;
			ZEPHIR_SINIT_NVAR(_4);
			ZVAL_LONG(&_4, i);
			ZEPHIR_SINIT_NVAR(_5);
			ZVAL_LONG(&_5, (i + 1));
			ZEPHIR_CALL_INTERNAL_METHOD_P2(&_3, this_ptr, zep_Test_McallInternal_other, &_4, &_5);
			zephir_check_call_status();
			p += zephir_get_doubleval(_3);
		}
	}
	RETURN_MM_DOUBLE(p);

}

