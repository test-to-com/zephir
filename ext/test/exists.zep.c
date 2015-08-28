
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
#include "kernel/fcall.h"
#include "kernel/operators.h"
#include "kernel/memory.h"


ZEPHIR_INIT_CLASS(Test_Exists) {

	ZEPHIR_REGISTER_CLASS(Test, Exists, test, exists, test_exists_method_entry, 0);

	return SUCCESS;

}

PHP_METHOD(Test_Exists, testClassExists) {

	int ZEPHIR_LAST_CALL_STATUS;
	zend_bool autoload;
	zval *className, *autoload_param = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 1, &className, &autoload_param);

	if (!autoload_param) {
		autoload = 1;
	} else {
		autoload = zephir_get_boolval(autoload_param);
	}


	ZEPHIR_RETURN_CALL_FUNCTION("class_exists", NULL, 43, className, (autoload ? ZEPHIR_GLOBAL(global_true) : ZEPHIR_GLOBAL(global_false)));
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Exists, testInterfaceExists) {

	int ZEPHIR_LAST_CALL_STATUS;
	zend_bool autoload;
	zval *interfaceName, *autoload_param = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 1, &interfaceName, &autoload_param);

	if (!autoload_param) {
		autoload = 1;
	} else {
		autoload = zephir_get_boolval(autoload_param);
	}


	ZEPHIR_RETURN_CALL_FUNCTION("interface_exists", NULL, 44, interfaceName, (autoload ? ZEPHIR_GLOBAL(global_true) : ZEPHIR_GLOBAL(global_false)));
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Exists, testMethodExists) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *obj, *methodName;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 2, 0, &obj, &methodName);



	ZEPHIR_RETURN_CALL_FUNCTION("method_exists", NULL, 45, obj, methodName);
	zephir_check_call_status();
	RETURN_MM();

}

PHP_METHOD(Test_Exists, testFileExists) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *fileName;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &fileName);



	ZEPHIR_RETURN_CALL_FUNCTION("file_exists", NULL, 46, fileName);
	zephir_check_call_status();
	RETURN_MM();

}

