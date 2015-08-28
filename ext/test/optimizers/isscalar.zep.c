
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


ZEPHIR_INIT_CLASS(Test_Optimizers_IsScalar) {

	ZEPHIR_REGISTER_CLASS(Test\\Optimizers, IsScalar, test, optimizers_isscalar, test_optimizers_isscalar_method_entry, 0);

	return SUCCESS;

}

PHP_METHOD(Test_Optimizers_IsScalar, testIntVar) {

	zval _0;
	int a = 1, ZEPHIR_LAST_CALL_STATUS;

	ZEPHIR_MM_GROW();

	ZEPHIR_SINIT_VAR(_0);
	ZVAL_LONG(&_0, a);
	ZEPHIR_RETURN_CALL_FUNCTION("is_scalar", NULL, 83, &_0);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Optimizers_IsScalar, testDoubleVar) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval _0;
	double a = 1;

	ZEPHIR_MM_GROW();

	ZEPHIR_SINIT_VAR(_0);
	ZVAL_DOUBLE(&_0, a);
	ZEPHIR_RETURN_CALL_FUNCTION("is_scalar", NULL, 83, &_0);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Optimizers_IsScalar, testBoolVar) {

	int ZEPHIR_LAST_CALL_STATUS;
	zend_bool a = 1;

	ZEPHIR_MM_GROW();

	ZEPHIR_RETURN_CALL_FUNCTION("is_scalar", NULL, 83, (a ? ZEPHIR_GLOBAL(global_true) : ZEPHIR_GLOBAL(global_false)));
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Optimizers_IsScalar, testStringVar) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *a;

	ZEPHIR_MM_GROW();
	ZEPHIR_INIT_VAR(a);
	ZVAL_STRING(a, "test string", 1);

	ZEPHIR_RETURN_CALL_FUNCTION("is_scalar", NULL, 83, a);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Optimizers_IsScalar, testEmptyArrayVar) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *a;

	ZEPHIR_MM_GROW();
	ZEPHIR_INIT_VAR(a);
	array_init(a);

	ZEPHIR_RETURN_CALL_FUNCTION("is_scalar", NULL, 83, a);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Optimizers_IsScalar, testVar) {

	zval _0;
	int a = 1, ZEPHIR_LAST_CALL_STATUS;

	ZEPHIR_MM_GROW();

	ZEPHIR_SINIT_VAR(_0);
	ZVAL_LONG(&_0, a);
	ZEPHIR_RETURN_CALL_FUNCTION("is_scalar", NULL, 83, &_0);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Optimizers_IsScalar, testVarParameter) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *a;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &a);



	ZEPHIR_RETURN_CALL_FUNCTION("is_scalar", NULL, 83, a);
	zephir_check_call_status();
	RETURN_MM();

}

