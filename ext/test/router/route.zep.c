
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
#include "kernel/object.h"
#include "kernel/memory.h"
#include "kernel/operators.h"
#include "kernel/concat.h"
#include "kernel/array.h"
#include "kernel/exception.h"
#include "kernel/hash.h"


/**
 * Test\Router\Route
 *
 * This class represents every route added to the router
 */
ZEPHIR_INIT_CLASS(Test_Router_Route) {

	ZEPHIR_REGISTER_CLASS(Test\\Router, Route, test, router_route, test_router_route_method_entry, 0);

	zend_declare_property_null(test_router_route_ce, SL("_pattern"), ZEND_ACC_PROTECTED TSRMLS_CC);

	zend_declare_property_null(test_router_route_ce, SL("_compiledPattern"), ZEND_ACC_PROTECTED TSRMLS_CC);

	zend_declare_property_null(test_router_route_ce, SL("_paths"), ZEND_ACC_PROTECTED TSRMLS_CC);

	zend_declare_property_null(test_router_route_ce, SL("_methods"), ZEND_ACC_PROTECTED TSRMLS_CC);

	zend_declare_property_null(test_router_route_ce, SL("_hostname"), ZEND_ACC_PROTECTED TSRMLS_CC);

	zend_declare_property_null(test_router_route_ce, SL("_converters"), ZEND_ACC_PROTECTED TSRMLS_CC);

	zend_declare_property_null(test_router_route_ce, SL("_id"), ZEND_ACC_PROTECTED TSRMLS_CC);

	zend_declare_property_null(test_router_route_ce, SL("_name"), ZEND_ACC_PROTECTED TSRMLS_CC);

	zend_declare_property_null(test_router_route_ce, SL("_beforeMatch"), ZEND_ACC_PROTECTED TSRMLS_CC);

	return SUCCESS;

}

/**
 * Test\Router\Route constructor
 *
 * @param string pattern
 * @param array paths
 * @param array|string httpMethods
 */
PHP_METHOD(Test_Router_Route, __construct) {

	int ZEPHIR_LAST_CALL_STATUS;
	zval *pattern, *paths = NULL, *httpMethods = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 2, &pattern, &paths, &httpMethods);

	if (!paths) {
		paths = ZEPHIR_GLOBAL(global_null);
	}
	if (!httpMethods) {
		httpMethods = ZEPHIR_GLOBAL(global_null);
	}


	ZEPHIR_CALL_METHOD(NULL, this_ptr, "reconfigure", NULL, 0, pattern, paths);
	zephir_check_call_status();
	zephir_update_property_this(this_ptr, SL("_methods"), httpMethods TSRMLS_CC);
	ZEPHIR_MM_RESTORE();

}

/**
 * Replaces placeholders from pattern returning a valid PCRE regular expression
 *
 * @param string pattern
 * @return string
 */
PHP_METHOD(Test_Router_Route, compilePattern) {

	int ZEPHIR_LAST_CALL_STATUS;
	zephir_fcall_cache_entry *_2 = NULL, *_5 = NULL;
	zval *pattern = NULL, *idPattern, *_0 = NULL, *_1 = NULL, *_3 = NULL, *_4 = NULL, *_6 = NULL, *_7 = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &pattern);

	ZEPHIR_SEPARATE_PARAM(pattern);


	ZEPHIR_INIT_VAR(_0);
	ZVAL_STRING(_0, ":", ZEPHIR_TEMP_PARAM_COPY);
	ZEPHIR_CALL_FUNCTION(&_1, "memstr", &_2, 96, pattern, _0);
	zephir_check_temp_parameter(_0);
	zephir_check_call_status();
	if (zephir_is_true(_1)) {
		ZEPHIR_INIT_VAR(idPattern);
		ZVAL_STRING(idPattern, "/([a-zA-Z0-9\\_\\-]+)", 1);
		ZEPHIR_INIT_NVAR(_0);
		ZVAL_STRING(_0, "/:module", ZEPHIR_TEMP_PARAM_COPY);
		ZEPHIR_CALL_FUNCTION(&_3, "memstr", &_2, 96, pattern, _0);
		zephir_check_temp_parameter(_0);
		zephir_check_call_status();
		if (zephir_is_true(_3)) {
			ZEPHIR_INIT_NVAR(_0);
			ZVAL_STRING(_0, "/:module", ZEPHIR_TEMP_PARAM_COPY);
			ZEPHIR_CALL_FUNCTION(&_4, "str_replace", &_5, 99, _0, idPattern, pattern);
			zephir_check_temp_parameter(_0);
			zephir_check_call_status();
			ZEPHIR_CPY_WRT(pattern, _4);
		}
		ZEPHIR_INIT_NVAR(_0);
		ZVAL_STRING(_0, "/:controller", ZEPHIR_TEMP_PARAM_COPY);
		ZEPHIR_CALL_FUNCTION(&_4, "memstr", &_2, 96, pattern, _0);
		zephir_check_temp_parameter(_0);
		zephir_check_call_status();
		if (zephir_is_true(_4)) {
			ZEPHIR_INIT_NVAR(_0);
			ZVAL_STRING(_0, "/:controller", ZEPHIR_TEMP_PARAM_COPY);
			ZEPHIR_CALL_FUNCTION(&_6, "str_replace", &_5, 99, _0, idPattern, pattern);
			zephir_check_temp_parameter(_0);
			zephir_check_call_status();
			ZEPHIR_CPY_WRT(pattern, _6);
		}
		ZEPHIR_INIT_NVAR(_0);
		ZVAL_STRING(_0, "/:namespace", ZEPHIR_TEMP_PARAM_COPY);
		ZEPHIR_CALL_FUNCTION(&_4, "memstr", &_2, 96, pattern, _0);
		zephir_check_temp_parameter(_0);
		zephir_check_call_status();
		if (zephir_is_true(_4)) {
			ZEPHIR_INIT_NVAR(_0);
			ZVAL_STRING(_0, "/:namespace", ZEPHIR_TEMP_PARAM_COPY);
			ZEPHIR_CALL_FUNCTION(&_6, "str_replace", &_5, 99, _0, idPattern, pattern);
			zephir_check_temp_parameter(_0);
			zephir_check_call_status();
			ZEPHIR_CPY_WRT(pattern, _6);
		}
		ZEPHIR_INIT_NVAR(_0);
		ZVAL_STRING(_0, "/:action", ZEPHIR_TEMP_PARAM_COPY);
		ZEPHIR_CALL_FUNCTION(&_4, "memstr", &_2, 96, pattern, _0);
		zephir_check_temp_parameter(_0);
		zephir_check_call_status();
		if (zephir_is_true(_4)) {
			ZEPHIR_INIT_NVAR(_0);
			ZVAL_STRING(_0, "/:action", ZEPHIR_TEMP_PARAM_COPY);
			ZEPHIR_CALL_FUNCTION(&_6, "str_replace", &_5, 99, _0, idPattern, pattern);
			zephir_check_temp_parameter(_0);
			zephir_check_call_status();
			ZEPHIR_CPY_WRT(pattern, _6);
		}
		ZEPHIR_INIT_NVAR(_0);
		ZVAL_STRING(_0, "/:params", ZEPHIR_TEMP_PARAM_COPY);
		ZEPHIR_CALL_FUNCTION(&_4, "memstr", &_2, 96, pattern, _0);
		zephir_check_temp_parameter(_0);
		zephir_check_call_status();
		if (zephir_is_true(_4)) {
			ZEPHIR_INIT_NVAR(_0);
			ZVAL_STRING(_0, "/:params", ZEPHIR_TEMP_PARAM_COPY);
			ZEPHIR_INIT_VAR(_7);
			ZVAL_STRING(_7, "(/.*)*", ZEPHIR_TEMP_PARAM_COPY);
			ZEPHIR_CALL_FUNCTION(&_6, "str_replace", &_5, 99, _0, _7, pattern);
			zephir_check_temp_parameter(_0);
			zephir_check_temp_parameter(_7);
			zephir_check_call_status();
			ZEPHIR_CPY_WRT(pattern, _6);
		}
		ZEPHIR_INIT_NVAR(_0);
		ZVAL_STRING(_0, "/:int", ZEPHIR_TEMP_PARAM_COPY);
		ZEPHIR_CALL_FUNCTION(&_4, "memstr", &_2, 96, pattern, _0);
		zephir_check_temp_parameter(_0);
		zephir_check_call_status();
		if (zephir_is_true(_4)) {
			ZEPHIR_INIT_NVAR(_0);
			ZVAL_STRING(_0, "/:int", ZEPHIR_TEMP_PARAM_COPY);
			ZEPHIR_INIT_NVAR(_7);
			ZVAL_STRING(_7, "/([0-9]+)", ZEPHIR_TEMP_PARAM_COPY);
			ZEPHIR_CALL_FUNCTION(&_6, "str_replace", &_5, 99, _0, _7, pattern);
			zephir_check_temp_parameter(_0);
			zephir_check_temp_parameter(_7);
			zephir_check_call_status();
			ZEPHIR_CPY_WRT(pattern, _6);
		}
	}
	ZEPHIR_INIT_NVAR(_0);
	ZVAL_STRING(_0, "(", ZEPHIR_TEMP_PARAM_COPY);
	ZEPHIR_CALL_FUNCTION(&_3, "memstr", &_2, 96, pattern, _0);
	zephir_check_temp_parameter(_0);
	zephir_check_call_status();
	if (zephir_is_true(_3)) {
		ZEPHIR_CONCAT_SVS(return_value, "#^", pattern, "$#");
		RETURN_MM();
	}
	ZEPHIR_INIT_NVAR(_0);
	ZVAL_STRING(_0, "[", ZEPHIR_TEMP_PARAM_COPY);
	ZEPHIR_CALL_FUNCTION(&_3, "memstr", &_2, 96, pattern, _0);
	zephir_check_temp_parameter(_0);
	zephir_check_call_status();
	if (zephir_is_true(_3)) {
		ZEPHIR_CONCAT_SVS(return_value, "#^", pattern, "$#");
		RETURN_MM();
	}
	RETVAL_ZVAL(pattern, 1, 0);
	RETURN_MM();

}

/**
 * Set one or more HTTP methods that constraint the matching of the route
 *
 *<code>
 * $route->via('GET');
 * $route->via(array('GET', 'POST'));
 *</code>
 *
 * @param string|array httpMethods
 * @return Test\Router\Route
 */
PHP_METHOD(Test_Router_Route, via) {

	zval *httpMethods;

	zephir_fetch_params(0, 1, 0, &httpMethods);



	zephir_update_property_this(this_ptr, SL("_methods"), httpMethods TSRMLS_CC);
	RETURN_THISW();

}

/**
 * Extracts parameters from a string
 *
 * @param string pattern
 */
PHP_METHOD(Test_Router_Route, extractNamedParams) {

	zephir_fcall_cache_entry *_5 = NULL;
	long _1, _7, _24;
	zend_bool notValid = 0, _8, _9, _10, _11, _12, _13, _14, _15, _16, _17, _18, _19;
	int tmp, cursor, cursorVar, marker, bracketCount = 0, parenthesesCount = 0, foundPattern = 0, intermediate = 0, numberMatches = 0, ZEPHIR_LAST_CALL_STATUS;
	char ch;
	zval *pattern_param = NULL, *matches, *_0 = NULL, _2 = zval_used_for_init, _3 = zval_used_for_init, *_4 = NULL, *_20 = NULL, *_22 = NULL, *_25 = NULL;
	zval *pattern = NULL, *route, *item = NULL, *variable = NULL, *regexp = NULL, *_6 = NULL, *_21 = NULL, *_23 = NULL, *_26 = NULL;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 0, &pattern_param);

	zephir_get_strval(pattern, pattern_param);


	ZEPHIR_CALL_FUNCTION(&_0, "strlen", NULL, 21, pattern);
	zephir_check_call_status();
	if (ZEPHIR_LE_LONG(_0, 0)) {
		RETURN_MM_BOOL(0);
	}
	ZEPHIR_INIT_VAR(matches);
	array_init(matches);
	ZEPHIR_INIT_VAR(route);
	ZVAL_EMPTY_STRING(route);
	for (_1 = 0; _1 < Z_STRLEN_P(pattern); _1++) {
		cursor = _1; 
		ch = ZEPHIR_STRING_OFFSET(pattern, _1);
		if (parenthesesCount == 0) {
			if (ch == '{') {
				if (bracketCount == 0) {
					marker = (cursor + 1);
					intermediate = 0;
					notValid = 0;
				}
				bracketCount++;
			} else {
				if (ch == '}') {
					bracketCount--;
					if (intermediate > 0) {
						if (bracketCount == 0) {
							numberMatches++;
							ZEPHIR_INIT_NVAR(variable);
							ZVAL_EMPTY_STRING(variable);
							ZEPHIR_INIT_NVAR(regexp);
							ZVAL_EMPTY_STRING(regexp);
							ZEPHIR_SINIT_NVAR(_2);
							ZVAL_LONG(&_2, marker);
							ZEPHIR_SINIT_NVAR(_3);
							ZVAL_LONG(&_3, (cursor - marker));
							ZEPHIR_CALL_FUNCTION(&_4, "substr", &_5, 48, pattern, &_2, &_3);
							zephir_check_call_status();
							zephir_get_strval(_6, _4);
							ZEPHIR_CPY_WRT(item, _6);
							for (_7 = 0; _7 < Z_STRLEN_P(item); _7++) {
								cursorVar = _7; 
								ch = ZEPHIR_STRING_OFFSET(item, _7);
								if (ch == '\0') {
									break;
								}
								_8 = cursorVar == 0;
								if (_8) {
									_9 = ch >= 'a';
									if (_9) {
										_9 = ch <= 'z';
									}
									_10 = _9;
									if (!(_10)) {
										_11 = ch >= 'A';
										if (_11) {
											_11 = ch <= 'Z';
										}
										_10 = _11;
									}
									_8 = !(_10);
								}
								if (_8) {
									notValid = 1;
									break;
								}
								_12 = ch >= 'a';
								if (_12) {
									_12 = ch <= 'z';
								}
								_13 = _12;
								if (!(_13)) {
									_14 = ch >= 'A';
									if (_14) {
										_14 = ch <= 'Z';
									}
									_13 = _14;
								}
								_15 = _13;
								if (!(_15)) {
									_16 = ch >= '0';
									if (_16) {
										_16 = ch <= '9';
									}
									_15 = _16;
								}
								_17 = _15;
								if (!(_17)) {
									_17 = ch == '-';
								}
								_18 = _17;
								if (!(_18)) {
									_18 = ch == '_';
								}
								_19 = _18;
								if (!(_19)) {
									_19 = ch == ':';
								}
								if (_19) {
									if (ch == ':') {
										ZEPHIR_SINIT_NVAR(_2);
										ZVAL_LONG(&_2, 0);
										ZEPHIR_SINIT_NVAR(_3);
										ZVAL_LONG(&_3, cursorVar);
										ZEPHIR_CALL_FUNCTION(&_20, "substr", &_5, 48, item, &_2, &_3);
										zephir_check_call_status();
										zephir_get_strval(_21, _20);
										ZEPHIR_CPY_WRT(variable, _21);
										ZEPHIR_SINIT_NVAR(_2);
										ZVAL_LONG(&_2, (cursorVar + 1));
										ZEPHIR_CALL_FUNCTION(&_22, "substr", &_5, 48, item, &_2);
										zephir_check_call_status();
										zephir_get_strval(_23, _22);
										ZEPHIR_CPY_WRT(regexp, _23);
										break;
									}
								} else {
									notValid = 1;
									break;
								}
							}
							if (!(notValid)) {
								tmp = numberMatches;
								_8 = zephir_is_true(variable);
								if (_8) {
									_8 = zephir_is_true(regexp);
								}
								if (_8) {
									foundPattern = 0;
									for (_24 = 0; _24 < Z_STRLEN_P(regexp); _24++) {
										ch = ZEPHIR_STRING_OFFSET(regexp, _24);
										if (ch == '\0') {
											break;
										}
										if (!(foundPattern)) {
											if (ch == '(') {
												foundPattern = 1;
											}
										} else {
											if (ch == ')') {
												foundPattern = 2;
												break;
											}
										}
									}
									if (foundPattern != 2) {
										zephir_concat_self_str(&route, "(", sizeof("(")-1 TSRMLS_CC);
										zephir_concat_self(&route, regexp TSRMLS_CC);
										zephir_concat_self_str(&route, ")", sizeof(")")-1 TSRMLS_CC);
									} else {
										zephir_concat_self(&route, regexp TSRMLS_CC);
									}
									ZEPHIR_INIT_NVAR(_25);
									ZVAL_LONG(_25, tmp);
									zephir_array_update_zval(&matches, variable, &_25, PH_COPY | PH_SEPARATE);
								} else {
									zephir_concat_self_str(&route, "([^/]*)", sizeof("([^/]*)")-1 TSRMLS_CC);
									ZEPHIR_INIT_NVAR(_25);
									ZVAL_LONG(_25, tmp);
									zephir_array_update_zval(&matches, item, &_25, PH_COPY | PH_SEPARATE);
								}
							} else {
								ZEPHIR_INIT_LNVAR(_26);
								ZEPHIR_CONCAT_SVS(_26, "{", item, "}");
								zephir_concat_self(&route, _26 TSRMLS_CC);
							}
							continue;
						}
					}
				}
			}
		}
		if (bracketCount == 0) {
			if (ch == '(') {
				parenthesesCount++;
			} else {
				if (ch == ')') {
					parenthesesCount--;
					if (parenthesesCount == 0) {
						numberMatches++;
					}
				}
			}
		}
		if (bracketCount > 0) {
			intermediate++;
		} else {
			zephir_concat_self_char(&route, ch TSRMLS_CC);
		}
	}
	zephir_create_array(return_value, 2, 0 TSRMLS_CC);
	zephir_array_fast_append(return_value, route);
	zephir_array_fast_append(return_value, matches);
	RETURN_MM();

}

/**
 * Reconfigure the route adding a new pattern and a set of paths
 *
 * @param string pattern
 * @param array paths
 */
PHP_METHOD(Test_Router_Route, reConfigure) {

	zephir_fcall_cache_entry *_4 = NULL;
	int ZEPHIR_LAST_CALL_STATUS;
	zval *pattern, *paths = NULL, *moduleName = NULL, *controllerName = NULL, *actionName = NULL, *parts = NULL, *routePaths = NULL, *realClassName = NULL, *namespaceName = NULL, *pcrePattern = NULL, *compiledPattern = NULL, *extracted = NULL, _0, *_1 = NULL, *_2 = NULL, *_3 = NULL, *_5 = NULL, *_6;

	ZEPHIR_MM_GROW();
	zephir_fetch_params(1, 1, 1, &pattern, &paths);

	if (!paths) {
		paths = ZEPHIR_GLOBAL(global_null);
	}


	if (Z_TYPE_P(pattern) != IS_STRING) {
		ZEPHIR_THROW_EXCEPTION_DEBUG_STR(test_router_exception_ce, "The pattern must be string", "test/router/route.zep", 270);
		return;
	}
	if (Z_TYPE_P(paths) != IS_NULL) {
		if (Z_TYPE_P(paths) == IS_STRING) {
			ZEPHIR_INIT_VAR(moduleName);
			ZVAL_NULL(moduleName);
			ZEPHIR_INIT_VAR(controllerName);
			ZVAL_NULL(controllerName);
			ZEPHIR_INIT_VAR(actionName);
			ZVAL_NULL(actionName);
			ZEPHIR_SINIT_VAR(_0);
			ZVAL_STRING(&_0, "::", 0);
			ZEPHIR_CALL_FUNCTION(&parts, "explode", NULL, 95, &_0, paths);
			zephir_check_call_status();
			ZEPHIR_CALL_FUNCTION(&_1, "count", NULL, 3, parts);
			zephir_check_call_status();
			do {
				if (ZEPHIR_IS_LONG(_1, 3)) {
					ZEPHIR_OBS_NVAR(moduleName);
					zephir_array_fetch_long(&moduleName, parts, 0, PH_NOISY, "test/router/route.zep", 286 TSRMLS_CC);
					ZEPHIR_OBS_NVAR(controllerName);
					zephir_array_fetch_long(&controllerName, parts, 1, PH_NOISY, "test/router/route.zep", 287 TSRMLS_CC);
					ZEPHIR_OBS_NVAR(actionName);
					zephir_array_fetch_long(&actionName, parts, 2, PH_NOISY, "test/router/route.zep", 288 TSRMLS_CC);
					break;
				}
				if (ZEPHIR_IS_LONG(_1, 2)) {
					ZEPHIR_OBS_NVAR(controllerName);
					zephir_array_fetch_long(&controllerName, parts, 0, PH_NOISY, "test/router/route.zep", 291 TSRMLS_CC);
					ZEPHIR_OBS_NVAR(actionName);
					zephir_array_fetch_long(&actionName, parts, 1, PH_NOISY, "test/router/route.zep", 292 TSRMLS_CC);
					break;
				}
				if (ZEPHIR_IS_LONG(_1, 1)) {
					ZEPHIR_OBS_NVAR(controllerName);
					zephir_array_fetch_long(&controllerName, parts, 0, PH_NOISY, "test/router/route.zep", 295 TSRMLS_CC);
					break;
				}
			} while(0);

			ZEPHIR_INIT_VAR(routePaths);
			array_init(routePaths);
			if (Z_TYPE_P(moduleName) != IS_NULL) {
				zephir_array_update_string(&routePaths, SL("module"), &moduleName, PH_COPY | PH_SEPARATE);
			}
			if (Z_TYPE_P(controllerName) != IS_NULL) {
				ZEPHIR_INIT_VAR(_2);
				ZVAL_STRING(_2, "\\", ZEPHIR_TEMP_PARAM_COPY);
				ZEPHIR_CALL_FUNCTION(&_3, "memstr", &_4, 96, controllerName, _2);
				zephir_check_temp_parameter(_2);
				zephir_check_call_status();
				if (zephir_is_true(_3)) {
					ZEPHIR_CALL_FUNCTION(&realClassName, "get_class_ns", NULL, 100, controllerName);
					zephir_check_call_status();
					ZEPHIR_CALL_FUNCTION(&namespaceName, "get_ns_class", NULL, 101, controllerName);
					zephir_check_call_status();
					if (zephir_is_true(namespaceName)) {
						zephir_array_update_string(&routePaths, SL("namespace"), &namespaceName, PH_COPY | PH_SEPARATE);
					}
				} else {
					ZEPHIR_CPY_WRT(realClassName, controllerName);
				}
				ZEPHIR_CALL_FUNCTION(&_5, "uncamelize", NULL, 102, realClassName);
				zephir_check_call_status();
				zephir_array_update_string(&routePaths, SL("controller"), &_5, PH_COPY | PH_SEPARATE);
			}
			if (Z_TYPE_P(actionName) != IS_NULL) {
				zephir_array_update_string(&routePaths, SL("action"), &actionName, PH_COPY | PH_SEPARATE);
			}
		} else {
			ZEPHIR_CPY_WRT(routePaths, paths);
		}
	} else {
		ZEPHIR_INIT_NVAR(routePaths);
		array_init(routePaths);
	}
	if (Z_TYPE_P(routePaths) != IS_ARRAY) {
		ZEPHIR_THROW_EXCEPTION_DEBUG_STR(test_router_exception_ce, "The route contains invalid paths", "test/router/route.zep", 342);
		return;
	}
	ZEPHIR_INIT_NVAR(_2);
	ZVAL_STRING(_2, "#", ZEPHIR_TEMP_PARAM_COPY);
	ZEPHIR_CALL_FUNCTION(&_1, "starts_with", NULL, 103, pattern, _2);
	zephir_check_temp_parameter(_2);
	zephir_check_call_status();
	if (!(zephir_is_true(_1))) {
		ZEPHIR_INIT_NVAR(_2);
		ZVAL_STRING(_2, "{", ZEPHIR_TEMP_PARAM_COPY);
		ZEPHIR_CALL_FUNCTION(&_3, "memstr", &_4, 96, pattern, _2);
		zephir_check_temp_parameter(_2);
		zephir_check_call_status();
		if (zephir_is_true(_3)) {
			ZEPHIR_CALL_METHOD(&extracted, this_ptr, "extractnamedparams", NULL, 0, pattern);
			zephir_check_call_status();
			ZEPHIR_OBS_VAR(pcrePattern);
			zephir_array_fetch_long(&pcrePattern, extracted, 0, PH_NOISY, "test/router/route.zep", 351 TSRMLS_CC);
			zephir_array_fetch_long(&_6, extracted, 1, PH_NOISY | PH_READONLY, "test/router/route.zep", 352 TSRMLS_CC);
			ZEPHIR_CALL_FUNCTION(&_5, "array_merge", NULL, 84, routePaths, _6);
			zephir_check_call_status();
			ZEPHIR_CPY_WRT(routePaths, _5);
		} else {
			ZEPHIR_CPY_WRT(pcrePattern, pattern);
		}
		ZEPHIR_CALL_METHOD(&compiledPattern, this_ptr, "compilepattern", NULL, 0, pcrePattern);
		zephir_check_call_status();
	} else {
		ZEPHIR_CPY_WRT(compiledPattern, pattern);
	}
	zephir_update_property_this(this_ptr, SL("_pattern"), pattern TSRMLS_CC);
	zephir_update_property_this(this_ptr, SL("_compiledPattern"), compiledPattern TSRMLS_CC);
	zephir_update_property_this(this_ptr, SL("_paths"), routePaths TSRMLS_CC);
	ZEPHIR_MM_RESTORE();

}

/**
 * Returns the route's name
 *
 * @return string
 */
PHP_METHOD(Test_Router_Route, getName) {


	RETURN_MEMBER(this_ptr, "_name");

}

/**
 * Sets the route's name
 *
 *<code>
 * $router->add('/about', array(
 *     'controller' => 'about'
 * ))->setName('about');
 *</code>
 *
 * @param string name
 * @return Route
 */
PHP_METHOD(Test_Router_Route, setName) {

	zval *name;

	zephir_fetch_params(0, 1, 0, &name);



	zephir_update_property_this(this_ptr, SL("_name"), name TSRMLS_CC);
	RETURN_THISW();

}

/**
 * Sets a callback that is called if the route is matched.
 * The developer can implement any arbitrary conditions here
 * If the callback returns false the route is treaded as not matched
 *
 * @param callback callback
 * @return Test\Router\Route
 */
PHP_METHOD(Test_Router_Route, beforeMatch) {

	zval *callback;

	zephir_fetch_params(0, 1, 0, &callback);



	zephir_update_property_this(this_ptr, SL("_beforeMatch"), callback TSRMLS_CC);
	RETURN_THISW();

}

/**
 * Returns the 'before match' callback if any
 *
 * @return mixed
 */
PHP_METHOD(Test_Router_Route, getBeforeMatch) {


	RETURN_MEMBER(this_ptr, "_beforeMatch");

}

/**
 * Returns the route's id
 *
 * @return string
 */
PHP_METHOD(Test_Router_Route, getRouteId) {


	RETURN_MEMBER(this_ptr, "_id");

}

/**
 * Returns the route's pattern
 *
 * @return string
 */
PHP_METHOD(Test_Router_Route, getPattern) {


	RETURN_MEMBER(this_ptr, "_pattern");

}

/**
 * Returns the route's compiled pattern
 *
 * @return string
 */
PHP_METHOD(Test_Router_Route, getCompiledPattern) {


	RETURN_MEMBER(this_ptr, "_compiledPattern");

}

/**
 * Returns the paths
 *
 * @return array
 */
PHP_METHOD(Test_Router_Route, getPaths) {


	RETURN_MEMBER(this_ptr, "_paths");

}

/**
 * Returns the paths using positions as keys and names as values
 *
 * @return array
 */
PHP_METHOD(Test_Router_Route, getReversedPaths) {

	HashTable *_2;
	HashPosition _1;
	zval *reversed, *path = NULL, *position = NULL, *_0, **_3;

	ZEPHIR_MM_GROW();

	ZEPHIR_INIT_VAR(reversed);
	array_init(reversed);
	_0 = zephir_fetch_nproperty_this(this_ptr, SL("_paths"), PH_NOISY_CC);
	zephir_is_iterable(_0, &_2, &_1, 0, 0, "test/router/route.zep", 478);
	for (
	  ; zephir_hash_get_current_data_ex(_2, (void**) &_3, &_1) == SUCCESS
	  ; zephir_hash_move_forward_ex(_2, &_1)
	) {
		ZEPHIR_GET_HMKEY(path, _2, _1);
		ZEPHIR_GET_HVALUE(position, _3);
		zephir_array_update_zval(&reversed, position, &path, PH_COPY | PH_SEPARATE);
	}
	RETURN_CCTOR(reversed);

}

/**
 * Sets a set of HTTP methods that constraint the matching of the route (alias of via)
 *
 *<code>
 * $route->setHttpMethods('GET');
 * $route->setHttpMethods(array('GET', 'POST'));
 *</code>
 *
 * @param string|array httpMethods
 * @return Test\Router\Route
 */
PHP_METHOD(Test_Router_Route, setHttpMethods) {

	zval *httpMethods;

	zephir_fetch_params(0, 1, 0, &httpMethods);



	zephir_update_property_this(this_ptr, SL("_methods"), httpMethods TSRMLS_CC);
	RETURN_THISW();

}

/**
 * Returns the HTTP methods that constraint matching the route
 *
 * @return string|array
 */
PHP_METHOD(Test_Router_Route, getHttpMethods) {


	RETURN_MEMBER(this_ptr, "_methods");

}

/**
 * Sets a hostname restriction to the route
 *
 *<code>
 * $route->setHostname('localhost');
 *</code>
 *
 * @param string|array httpMethods
 * @return Test\Router\Route
 */
PHP_METHOD(Test_Router_Route, setHostname) {

	zval *hostname;

	zephir_fetch_params(0, 1, 0, &hostname);



	zephir_update_property_this(this_ptr, SL("_hostname"), hostname TSRMLS_CC);
	RETURN_THISW();

}

/**
 * Returns the hostname restriction if any
 *
 * @return string
 */
PHP_METHOD(Test_Router_Route, getHostname) {


	RETURN_MEMBER(this_ptr, "_hostname");

}

/**
 * Adds a converter to perform an additional transformation for certain parameter
 *
 * @param string name
 * @param callable converter
 * @return Test\Router\Route
 */
PHP_METHOD(Test_Router_Route, convert) {

	zval *name, *converter;

	zephir_fetch_params(0, 2, 0, &name, &converter);



	zephir_update_property_array(this_ptr, SL("_converters"), name, converter TSRMLS_CC);
	RETURN_THISW();

}

/**
 * Returns the router converter
 *
 * @return array
 */
PHP_METHOD(Test_Router_Route, getConverters) {


	RETURN_MEMBER(this_ptr, "_converters");

}

