
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


ZEPHIR_INIT_CLASS(Test_ResourceTest) {

	ZEPHIR_REGISTER_CLASS(Test, ResourceTest, test, resourcetest, test_resourcetest_method_entry, 0);

	return SUCCESS;

}

PHP_METHOD(Test_ResourceTest, testLetStatementSTDIN) {

	zval *a = NULL;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(a);
	ZEPHIR_GET_CONSTANT(a, "STDIN");
	RETURN_CCTOR(a);

}

PHP_METHOD(Test_ResourceTest, testLetStatementSTDOUT) {

	zval *a = NULL;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(a);
	ZEPHIR_GET_CONSTANT(a, "STDOUT");
	RETURN_CCTOR(a);

}

PHP_METHOD(Test_ResourceTest, testLetStatementSTDERR) {

	zval *a = NULL;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(a);
	ZEPHIR_GET_CONSTANT(a, "STDERR");
	RETURN_CCTOR(a);

}

PHP_METHOD(Test_ResourceTest, testTypeOffResource) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *a = NULL, *_0 = NULL;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(a);
	ZEPHIR_GET_CONSTANT(a, "STDIN");
	ZEPHIR_CALL_FUNCTION(&_0, "gettype", NULL, 91, a);
	zephir_check_call_status();
	RETURN_CCTOR(_0);

}

PHP_METHOD(Test_ResourceTest, testIsResource) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *a = NULL;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(a);
	ZEPHIR_GET_CONSTANT(a, "STDIN");
	ZEPHIR_RETURN_CALL_FUNCTION("is_resource", NULL, 92, a);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_ResourceTest, testFunctionsForSTDIN) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *a = NULL, _0;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(a);
	ZEPHIR_GET_CONSTANT(a, "STDIN");
	ZEPHIR_SINIT_VAR(_0);
	ZVAL_LONG(&_0, 1);
	ZEPHIR_CALL_FUNCTION(NULL, "stream_set_blocking", NULL, 93, a, &_0);
	zephir_check_call_status();
	ZEPHIR_MM_RESTORE();

}

