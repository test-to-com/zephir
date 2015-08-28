
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
#include "kernel/fcall.h"
#include "kernel/memory.h"


ZEPHIR_INIT_CLASS(Test_Optimizers_Substr) {

	ZEPHIR_REGISTER_CLASS(Test\\Optimizers, Substr, test, optimizers_substr, test_optimizers_substr_method_entry, 0);

	return SUCCESS;

}

PHP_METHOD(Test_Optimizers_Substr, testTwoArguments) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str, *start;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 2, 0, &str, &start);



	ZEPHIR_RETURN_CALL_FUNCTION("substr", NULL, 48, str, start);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Optimizers_Substr, testThreeArguments) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *str, *start, *offset;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 3, 0, &str, &start, &offset);



	ZEPHIR_RETURN_CALL_FUNCTION("substr", NULL, 48, str, start, offset);
	zephir_check_call_status();
	RETURN_MM();

}

