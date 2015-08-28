
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
#include "kernel/operators.h"
#include "kernel/fcall.h"
#include "kernel/memory.h"


ZEPHIR_INIT_CLASS(Test_ExitDie) {

	ZEPHIR_REGISTER_CLASS(Test, ExitDie, test, exitdie, test_exitdie_method_entry, 0);

	return SUCCESS;

}

PHP_METHOD(Test_ExitDie, testExit) {

	int ZEPHIR_LAST_CALL_STATUS;
	zend_bool _0;
	zval *param = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 0, 1, &param);

	if (!param) {
		ZEPHIR_INIT_VAR(param);
		ZVAL_STRING(param, "", 1);
	}


	_0 = Z_TYPE_P(param) == IS_STRING;
	if (_0) {
		_0 = ZEPHIR_IS_STRING(param, "");
	}
	if (_0) {
		ZEPHIR_CALL_FUNCTION(NULL, "exit", NULL, 0);
		zephir_check_call_status();
	} else {
		ZEPHIR_CALL_FUNCTION(NULL, "exit", NULL, 0, param);
		zephir_check_call_status();
	}
	ZEPHIR_MM_RESTORE();

}

PHP_METHOD(Test_ExitDie, testDie) {

	int ZEPHIR_LAST_CALL_STATUS;
	zend_bool _0;
	zval *param = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 0, 1, &param);

	if (!param) {
		ZEPHIR_INIT_VAR(param);
		ZVAL_STRING(param, "", 1);
	}


	_0 = Z_TYPE_P(param) == IS_STRING;
	if (_0) {
		_0 = ZEPHIR_IS_STRING(param, "");
	}
	if (_0) {
		ZEPHIR_CALL_FUNCTION(NULL, "die", NULL, 0);
		zephir_check_call_status();
	} else {
		ZEPHIR_CALL_FUNCTION(NULL, "die", NULL, 0, param);
		zephir_check_call_status();
	}
	ZEPHIR_MM_RESTORE();

}

