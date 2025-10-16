<?php declare(strict_types = 1);

return [
	'lastFullAnalysisTime' => 1760633872,
	'meta' => array (
  'cacheVersion' => 'v12-linesToIgnore',
  'phpstanVersion' => '2.1.31',
  'metaExtensions' => 
  array (
  ),
  'phpVersion' => 80405,
  'projectConfig' => '{conditionalTags: {PHPStan\\Rules\\DisallowedConstructs\\DisallowedLooseComparisonRule: {phpstan.rules.rule: %strictRules.disallowedLooseComparison%}, PHPStan\\Rules\\BooleansInConditions\\BooleanInBooleanAndRule: {phpstan.rules.rule: %strictRules.booleansInConditions%}, PHPStan\\Rules\\BooleansInConditions\\BooleanInBooleanNotRule: {phpstan.rules.rule: %strictRules.booleansInConditions%}, PHPStan\\Rules\\BooleansInConditions\\BooleanInBooleanOrRule: {phpstan.rules.rule: %strictRules.booleansInConditions%}, PHPStan\\Rules\\BooleansInConditions\\BooleanInDoWhileConditionRule: {phpstan.rules.rule: %strictRules.booleansInLoopConditions%}, PHPStan\\Rules\\BooleansInConditions\\BooleanInElseIfConditionRule: {phpstan.rules.rule: %strictRules.booleansInConditions%}, PHPStan\\Rules\\BooleansInConditions\\BooleanInIfConditionRule: {phpstan.rules.rule: %strictRules.booleansInConditions%}, PHPStan\\Rules\\BooleansInConditions\\BooleanInTernaryOperatorRule: {phpstan.rules.rule: %strictRules.booleansInConditions%}, PHPStan\\Rules\\BooleansInConditions\\BooleanInWhileConditionRule: {phpstan.rules.rule: %strictRules.booleansInLoopConditions%}, PHPStan\\Rules\\Cast\\UselessCastRule: {phpstan.rules.rule: %strictRules.uselessCast%}, PHPStan\\Rules\\Classes\\RequireParentConstructCallRule: {phpstan.rules.rule: %strictRules.requireParentConstructorCall%}, PHPStan\\Rules\\DisallowedConstructs\\DisallowedBacktickRule: {phpstan.rules.rule: %strictRules.disallowedBacktick%}, PHPStan\\Rules\\DisallowedConstructs\\DisallowedEmptyRule: {phpstan.rules.rule: %strictRules.disallowedEmpty%}, PHPStan\\Rules\\DisallowedConstructs\\DisallowedImplicitArrayCreationRule: {phpstan.rules.rule: %strictRules.disallowedImplicitArrayCreation%}, PHPStan\\Rules\\DisallowedConstructs\\DisallowedShortTernaryRule: {phpstan.rules.rule: %strictRules.disallowedShortTernary%}, PHPStan\\Rules\\ForeachLoop\\OverwriteVariablesWithForeachRule: {phpstan.rules.rule: %strictRules.overwriteVariablesWithLoop%}, PHPStan\\Rules\\ForLoop\\OverwriteVariablesWithForLoopInitRule: {phpstan.rules.rule: %strictRules.overwriteVariablesWithLoop%}, PHPStan\\Rules\\Functions\\ArrayFilterStrictRule: {phpstan.rules.rule: %strictRules.strictArrayFilter%}, PHPStan\\Rules\\Functions\\ClosureUsesThisRule: {phpstan.rules.rule: %strictRules.closureUsesThis%}, PHPStan\\Rules\\Methods\\WrongCaseOfInheritedMethodRule: {phpstan.rules.rule: %strictRules.matchingInheritedMethodNames%}, PHPStan\\Rules\\Operators\\OperandInArithmeticPostDecrementRule: {phpstan.rules.rule: %strictRules.numericOperandsInArithmeticOperators%}, PHPStan\\Rules\\Operators\\OperandInArithmeticPostIncrementRule: {phpstan.rules.rule: %strictRules.numericOperandsInArithmeticOperators%}, PHPStan\\Rules\\Operators\\OperandInArithmeticPreDecrementRule: {phpstan.rules.rule: %strictRules.numericOperandsInArithmeticOperators%}, PHPStan\\Rules\\Operators\\OperandInArithmeticPreIncrementRule: {phpstan.rules.rule: %strictRules.numericOperandsInArithmeticOperators%}, PHPStan\\Rules\\Operators\\OperandInArithmeticUnaryMinusRule: {phpstan.rules.rule: [%strictRules.numericOperandsInArithmeticOperators%, %featureToggles.bleedingEdge%]}, PHPStan\\Rules\\Operators\\OperandInArithmeticUnaryPlusRule: {phpstan.rules.rule: [%strictRules.numericOperandsInArithmeticOperators%, %featureToggles.bleedingEdge%]}, PHPStan\\Rules\\Operators\\OperandsInArithmeticAdditionRule: {phpstan.rules.rule: %strictRules.numericOperandsInArithmeticOperators%}, PHPStan\\Rules\\Operators\\OperandsInArithmeticDivisionRule: {phpstan.rules.rule: %strictRules.numericOperandsInArithmeticOperators%}, PHPStan\\Rules\\Operators\\OperandsInArithmeticExponentiationRule: {phpstan.rules.rule: %strictRules.numericOperandsInArithmeticOperators%}, PHPStan\\Rules\\Operators\\OperandsInArithmeticModuloRule: {phpstan.rules.rule: %strictRules.numericOperandsInArithmeticOperators%}, PHPStan\\Rules\\Operators\\OperandsInArithmeticMultiplicationRule: {phpstan.rules.rule: %strictRules.numericOperandsInArithmeticOperators%}, PHPStan\\Rules\\Operators\\OperandsInArithmeticSubtractionRule: {phpstan.rules.rule: %strictRules.numericOperandsInArithmeticOperators%}, PHPStan\\Rules\\StrictCalls\\DynamicCallOnStaticMethodsRule: {phpstan.rules.rule: %strictRules.dynamicCallOnStaticMethod%}, PHPStan\\Rules\\StrictCalls\\DynamicCallOnStaticMethodsCallableRule: {phpstan.rules.rule: %strictRules.dynamicCallOnStaticMethod%}, PHPStan\\Rules\\StrictCalls\\StrictFunctionCallsRule: {phpstan.rules.rule: %strictRules.strictFunctionCalls%}, PHPStan\\Rules\\SwitchConditions\\MatchingTypeInSwitchCaseConditionRule: {phpstan.rules.rule: %strictRules.switchConditionsMatchingType%}, PHPStan\\Rules\\VariableVariables\\VariableMethodCallRule: {phpstan.rules.rule: %strictRules.noVariableVariables%}, PHPStan\\Rules\\VariableVariables\\VariableMethodCallableRule: {phpstan.rules.rule: %strictRules.noVariableVariables%}, PHPStan\\Rules\\VariableVariables\\VariableStaticMethodCallRule: {phpstan.rules.rule: %strictRules.noVariableVariables%}, PHPStan\\Rules\\VariableVariables\\VariableStaticMethodCallableRule: {phpstan.rules.rule: %strictRules.noVariableVariables%}, PHPStan\\Rules\\VariableVariables\\VariableStaticPropertyFetchRule: {phpstan.rules.rule: %strictRules.noVariableVariables%}, PHPStan\\Rules\\VariableVariables\\VariableVariablesRule: {phpstan.rules.rule: %strictRules.noVariableVariables%}, PHPStan\\Rules\\VariableVariables\\VariablePropertyFetchRule: {phpstan.rules.rule: %strictRules.noVariableVariables%}, PHPStan\\Rules\\Methods\\IllegalConstructorMethodCallRule: {phpstan.rules.rule: %strictRules.illegalConstructorMethodCall%}, PHPStan\\Rules\\Methods\\IllegalConstructorStaticCallRule: {phpstan.rules.rule: %strictRules.illegalConstructorMethodCall%}}, parameters: {strictRulesInstalled: true, polluteScopeWithLoopInitialAssignments: false, polluteScopeWithAlwaysIterableForeach: false, polluteScopeWithBlock: false, checkDynamicProperties: true, checkExplicitMixedMissingReturn: true, checkFunctionNameCase: true, checkInternalClassCaseSensitivity: true, reportMaybesInMethodSignatures: true, reportStaticMethodSignatures: true, reportMaybesInPropertyPhpDocTypes: true, reportWrongPhpDocTypeInVarTag: true, checkStrictPrintfPlaceholderTypes: true, strictRules: {allRules: true, disallowedLooseComparison: %strictRules.allRules%, booleansInConditions: %strictRules.allRules%, booleansInLoopConditions: [%strictRules.allRules%, %featureToggles.bleedingEdge%], uselessCast: %strictRules.allRules%, requireParentConstructorCall: %strictRules.allRules%, disallowedBacktick: %strictRules.allRules%, disallowedEmpty: %strictRules.allRules%, disallowedImplicitArrayCreation: %strictRules.allRules%, disallowedShortTernary: %strictRules.allRules%, overwriteVariablesWithLoop: %strictRules.allRules%, closureUsesThis: %strictRules.allRules%, matchingInheritedMethodNames: %strictRules.allRules%, numericOperandsInArithmeticOperators: %strictRules.allRules%, strictFunctionCalls: %strictRules.allRules%, dynamicCallOnStaticMethod: %strictRules.allRules%, switchConditionsMatchingType: %strictRules.allRules%, noVariableVariables: %strictRules.allRules%, strictArrayFilter: %strictRules.allRules%, illegalConstructorMethodCall: %strictRules.allRules%}, level: 9, paths: [/mnt/develop/underpixels/parallite/parallite-php/src], tmpDir: /mnt/develop/underpixels/parallite/parallite-php/build/phpstan}, services: [{class: PHPStan\\Rules\\BooleansInConditions\\BooleanRuleHelper}, {class: PHPStan\\Rules\\Operators\\OperatorRuleHelper}, {class: PHPStan\\Rules\\VariableVariables\\VariablePropertyFetchRule, arguments: {universalObjectCratesClasses: %universalObjectCratesClasses%}}, {class: PHPStan\\Rules\\DisallowedConstructs\\DisallowedLooseComparisonRule}, {class: PHPStan\\Rules\\BooleansInConditions\\BooleanInBooleanAndRule}, {class: PHPStan\\Rules\\BooleansInConditions\\BooleanInBooleanNotRule}, {class: PHPStan\\Rules\\BooleansInConditions\\BooleanInBooleanOrRule}, {class: PHPStan\\Rules\\BooleansInConditions\\BooleanInDoWhileConditionRule}, {class: PHPStan\\Rules\\BooleansInConditions\\BooleanInElseIfConditionRule}, {class: PHPStan\\Rules\\BooleansInConditions\\BooleanInIfConditionRule}, {class: PHPStan\\Rules\\BooleansInConditions\\BooleanInTernaryOperatorRule}, {class: PHPStan\\Rules\\BooleansInConditions\\BooleanInWhileConditionRule}, {class: PHPStan\\Rules\\Cast\\UselessCastRule, arguments: {treatPhpDocTypesAsCertain: %treatPhpDocTypesAsCertain%, treatPhpDocTypesAsCertainTip: %tips.treatPhpDocTypesAsCertain%}}, {class: PHPStan\\Rules\\Classes\\RequireParentConstructCallRule}, {class: PHPStan\\Rules\\DisallowedConstructs\\DisallowedBacktickRule}, {class: PHPStan\\Rules\\DisallowedConstructs\\DisallowedEmptyRule}, {class: PHPStan\\Rules\\DisallowedConstructs\\DisallowedImplicitArrayCreationRule}, {class: PHPStan\\Rules\\DisallowedConstructs\\DisallowedShortTernaryRule}, {class: PHPStan\\Rules\\ForeachLoop\\OverwriteVariablesWithForeachRule}, {class: PHPStan\\Rules\\ForLoop\\OverwriteVariablesWithForLoopInitRule}, {class: PHPStan\\Rules\\Functions\\ArrayFilterStrictRule, arguments: {treatPhpDocTypesAsCertain: %treatPhpDocTypesAsCertain%, checkNullables: %checkNullables%, treatPhpDocTypesAsCertainTip: %tips.treatPhpDocTypesAsCertain%}}, {class: PHPStan\\Rules\\Functions\\ClosureUsesThisRule}, {class: PHPStan\\Rules\\Methods\\WrongCaseOfInheritedMethodRule}, {class: PHPStan\\Rules\\Methods\\IllegalConstructorMethodCallRule}, {class: PHPStan\\Rules\\Methods\\IllegalConstructorStaticCallRule}, {class: PHPStan\\Rules\\Operators\\OperandInArithmeticPostDecrementRule}, {class: PHPStan\\Rules\\Operators\\OperandInArithmeticPostIncrementRule}, {class: PHPStan\\Rules\\Operators\\OperandInArithmeticPreDecrementRule}, {class: PHPStan\\Rules\\Operators\\OperandInArithmeticPreIncrementRule}, {class: PHPStan\\Rules\\Operators\\OperandInArithmeticUnaryMinusRule}, {class: PHPStan\\Rules\\Operators\\OperandInArithmeticUnaryPlusRule}, {class: PHPStan\\Rules\\Operators\\OperandsInArithmeticAdditionRule}, {class: PHPStan\\Rules\\Operators\\OperandsInArithmeticDivisionRule}, {class: PHPStan\\Rules\\Operators\\OperandsInArithmeticExponentiationRule}, {class: PHPStan\\Rules\\Operators\\OperandsInArithmeticModuloRule}, {class: PHPStan\\Rules\\Operators\\OperandsInArithmeticMultiplicationRule}, {class: PHPStan\\Rules\\Operators\\OperandsInArithmeticSubtractionRule}, {class: PHPStan\\Rules\\StrictCalls\\DynamicCallOnStaticMethodsRule}, {class: PHPStan\\Rules\\StrictCalls\\DynamicCallOnStaticMethodsCallableRule}, {class: PHPStan\\Rules\\StrictCalls\\StrictFunctionCallsRule}, {class: PHPStan\\Rules\\SwitchConditions\\MatchingTypeInSwitchCaseConditionRule}, {class: PHPStan\\Rules\\VariableVariables\\VariableMethodCallRule}, {class: PHPStan\\Rules\\VariableVariables\\VariableMethodCallableRule}, {class: PHPStan\\Rules\\VariableVariables\\VariableStaticMethodCallRule}, {class: PHPStan\\Rules\\VariableVariables\\VariableStaticMethodCallableRule}, {class: PHPStan\\Rules\\VariableVariables\\VariableStaticPropertyFetchRule}, {class: PHPStan\\Rules\\VariableVariables\\VariableVariablesRule}]}',
  'analysedPaths' => 
  array (
    0 => '/mnt/develop/underpixels/parallite/parallite-php/src',
  ),
  'scannedFiles' => 
  array (
  ),
  'composerLocks' => 
  array (
    '/mnt/develop/underpixels/parallite/parallite-php/composer.lock' => 'a7cdc5c142aeacee70f6302333c9c94cbbbd7a33',
  ),
  'composerInstalled' => 
  array (
    '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/installed.php' => 
    array (
      'versions' => 
      array (
        'brianium/paratest' => 
        array (
          'pretty_version' => 'v7.14.1',
          'version' => '7.14.1.0',
          'reference' => 'e1a93c38a94f4808faf75552e835666d3a6f8bb2',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../brianium/paratest',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'doctrine/deprecations' => 
        array (
          'pretty_version' => '1.1.5',
          'version' => '1.1.5.0',
          'reference' => '459c2f5dd3d6a4633d3b5f46ee2b1c40f57d3f38',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../doctrine/deprecations',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'fidry/cpu-core-counter' => 
        array (
          'pretty_version' => '1.3.0',
          'version' => '1.3.0.0',
          'reference' => 'db9508f7b1474469d9d3c53b86f817e344732678',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../fidry/cpu-core-counter',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'filp/whoops' => 
        array (
          'pretty_version' => '2.18.4',
          'version' => '2.18.4.0',
          'reference' => 'd2102955e48b9fd9ab24280a7ad12ed552752c4d',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../filp/whoops',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'jean85/pretty-package-versions' => 
        array (
          'pretty_version' => '2.1.1',
          'version' => '2.1.1.0',
          'reference' => '4d7aa5dab42e2a76d99559706022885de0e18e1a',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../jean85/pretty-package-versions',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'myclabs/deep-copy' => 
        array (
          'pretty_version' => '1.13.4',
          'version' => '1.13.4.0',
          'reference' => '07d290f0c47959fd5eed98c95ee5602db07e0b6a',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../myclabs/deep-copy',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'nette/utils' => 
        array (
          'pretty_version' => 'v4.0.8',
          'version' => '4.0.8.0',
          'reference' => 'c930ca4e3cf4f17dcfb03037703679d2396d2ede',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../nette/utils',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'nikic/php-parser' => 
        array (
          'pretty_version' => 'v5.6.1',
          'version' => '5.6.1.0',
          'reference' => 'f103601b29efebd7ff4a1ca7b3eeea9e3336a2a2',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../nikic/php-parser',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'nunomaduro/collision' => 
        array (
          'pretty_version' => 'v8.8.2',
          'version' => '8.8.2.0',
          'reference' => '60207965f9b7b7a4ce15a0f75d57f9dadb105bdb',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../nunomaduro/collision',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'nunomaduro/pokio' => 
        array (
          'pretty_version' => 'v0.1.1',
          'version' => '0.1.1.0',
          'reference' => '084ae842c9567a01b9693386e72bbf17ef086566',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../nunomaduro/pokio',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'nunomaduro/termwind' => 
        array (
          'pretty_version' => 'v2.3.1',
          'version' => '2.3.1.0',
          'reference' => 'dfa08f390e509967a15c22493dc0bac5733d9123',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../nunomaduro/termwind',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'opis/closure' => 
        array (
          'pretty_version' => '4.3.1',
          'version' => '4.3.1.0',
          'reference' => '9b6ef5f622b4b29cf98bdc144f2a370c3a40d4df',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../opis/closure',
          'aliases' => 
          array (
          ),
          'dev_requirement' => false,
        ),
        'pestphp/pest' => 
        array (
          'pretty_version' => 'v4.1.2',
          'version' => '4.1.2.0',
          'reference' => '08b09f2e98fc6830050c0237968b233768642d46',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../pestphp/pest',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'pestphp/pest-plugin' => 
        array (
          'pretty_version' => 'v4.0.0',
          'version' => '4.0.0.0',
          'reference' => '9d4b93d7f73d3f9c3189bb22c220fef271cdf568',
          'type' => 'composer-plugin',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../pestphp/pest-plugin',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'pestphp/pest-plugin-arch' => 
        array (
          'pretty_version' => 'v4.0.0',
          'version' => '4.0.0.0',
          'reference' => '25bb17e37920ccc35cbbcda3b00d596aadf3e58d',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../pestphp/pest-plugin-arch',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'pestphp/pest-plugin-mutate' => 
        array (
          'pretty_version' => 'v4.0.1',
          'version' => '4.0.1.0',
          'reference' => 'd9b32b60b2385e1688a68cc227594738ec26d96c',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../pestphp/pest-plugin-mutate',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'pestphp/pest-plugin-profanity' => 
        array (
          'pretty_version' => 'v4.1.0',
          'version' => '4.1.0.0',
          'reference' => 'e279c844b6868da92052be27b5202c2ad7216e80',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../pestphp/pest-plugin-profanity',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'pestphp/pest-plugin-type-coverage' => 
        array (
          'pretty_version' => 'v4.0.2',
          'version' => '4.0.2.0',
          'reference' => 'd7701b0b6d4412ad77eeda62ed266bdb9212d62d',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../pestphp/pest-plugin-type-coverage',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phar-io/manifest' => 
        array (
          'pretty_version' => '2.0.4',
          'version' => '2.0.4.0',
          'reference' => '54750ef60c58e43759730615a392c31c80e23176',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phar-io/manifest',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phar-io/version' => 
        array (
          'pretty_version' => '3.2.1',
          'version' => '3.2.1.0',
          'reference' => '4f7fd7836c6f332bb2933569e566a0d6c4cbed74',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phar-io/version',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpdocumentor/reflection-common' => 
        array (
          'pretty_version' => '2.2.0',
          'version' => '2.2.0.0',
          'reference' => '1d01c49d4ed62f25aa84a747ad35d5a16924662b',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpdocumentor/reflection-common',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpdocumentor/reflection-docblock' => 
        array (
          'pretty_version' => '5.6.3',
          'version' => '5.6.3.0',
          'reference' => '94f8051919d1b0369a6bcc7931d679a511c03fe9',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpdocumentor/reflection-docblock',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpdocumentor/type-resolver' => 
        array (
          'pretty_version' => '1.10.0',
          'version' => '1.10.0.0',
          'reference' => '679e3ce485b99e84c775d28e2e96fade9a7fb50a',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpdocumentor/type-resolver',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpstan/phpdoc-parser' => 
        array (
          'pretty_version' => '2.3.0',
          'version' => '2.3.0.0',
          'reference' => '1e0cd5370df5dd2e556a36b9c62f62e555870495',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpstan/phpdoc-parser',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpstan/phpstan' => 
        array (
          'pretty_version' => '2.1.31',
          'version' => '2.1.31.0',
          'reference' => 'ead89849d879fe203ce9292c6ef5e7e76f867b96',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpstan/phpstan',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpstan/phpstan-strict-rules' => 
        array (
          'pretty_version' => '2.0.7',
          'version' => '2.0.7.0',
          'reference' => 'd6211c46213d4181054b3d77b10a5c5cb0d59538',
          'type' => 'phpstan-extension',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpstan/phpstan-strict-rules',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-code-coverage' => 
        array (
          'pretty_version' => '12.4.0',
          'version' => '12.4.0.0',
          'reference' => '67e8aed88f93d0e6e1cb7effe1a2dfc2fee6022c',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpunit/php-code-coverage',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-file-iterator' => 
        array (
          'pretty_version' => '6.0.0',
          'version' => '6.0.0.0',
          'reference' => '961bc913d42fe24a257bfff826a5068079ac7782',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpunit/php-file-iterator',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-invoker' => 
        array (
          'pretty_version' => '6.0.0',
          'version' => '6.0.0.0',
          'reference' => '12b54e689b07a25a9b41e57736dfab6ec9ae5406',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpunit/php-invoker',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-text-template' => 
        array (
          'pretty_version' => '5.0.0',
          'version' => '5.0.0.0',
          'reference' => 'e1367a453f0eda562eedb4f659e13aa900d66c53',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpunit/php-text-template',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/php-timer' => 
        array (
          'pretty_version' => '8.0.0',
          'version' => '8.0.0.0',
          'reference' => 'f258ce36aa457f3aa3339f9ed4c81fc66dc8c2cc',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpunit/php-timer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'phpunit/phpunit' => 
        array (
          'pretty_version' => '12.4.0',
          'version' => '12.4.0.0',
          'reference' => 'f62aab5794e36ccd26860db2d1bbf89ac19028d9',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../phpunit/phpunit',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'psr/container' => 
        array (
          'pretty_version' => '2.0.2',
          'version' => '2.0.2.0',
          'reference' => 'c71ecc56dfe541dbd90c5360474fbc405f8d5963',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../psr/container',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'psr/log' => 
        array (
          'pretty_version' => '3.0.2',
          'version' => '3.0.2.0',
          'reference' => 'f16e1d5863e37f8d8c2a01719f5b34baa2b714d3',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../psr/log',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'psr/log-implementation' => 
        array (
          'dev_requirement' => true,
          'provided' => 
          array (
            0 => '1.0|2.0|3.0',
          ),
        ),
        'psr/simple-cache' => 
        array (
          'pretty_version' => '3.0.0',
          'version' => '3.0.0.0',
          'reference' => '764e0b3939f5ca87cb904f570ef9be2d78a07865',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../psr/simple-cache',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/cli-parser' => 
        array (
          'pretty_version' => '4.2.0',
          'version' => '4.2.0.0',
          'reference' => '90f41072d220e5c40df6e8635f5dafba2d9d4d04',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/cli-parser',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/comparator' => 
        array (
          'pretty_version' => '7.1.3',
          'version' => '7.1.3.0',
          'reference' => 'dc904b4bb3ab070865fa4068cd84f3da8b945148',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/comparator',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/complexity' => 
        array (
          'pretty_version' => '5.0.0',
          'version' => '5.0.0.0',
          'reference' => 'bad4316aba5303d0221f43f8cee37eb58d384bbb',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/complexity',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/diff' => 
        array (
          'pretty_version' => '7.0.0',
          'version' => '7.0.0.0',
          'reference' => '7ab1ea946c012266ca32390913653d844ecd085f',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/diff',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/environment' => 
        array (
          'pretty_version' => '8.0.3',
          'version' => '8.0.3.0',
          'reference' => '24a711b5c916efc6d6e62aa65aa2ec98fef77f68',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/environment',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/exporter' => 
        array (
          'pretty_version' => '7.0.2',
          'version' => '7.0.2.0',
          'reference' => '016951ae10980765e4e7aee491eb288c64e505b7',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/exporter',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/global-state' => 
        array (
          'pretty_version' => '8.0.2',
          'version' => '8.0.2.0',
          'reference' => 'ef1377171613d09edd25b7816f05be8313f9115d',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/global-state',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/lines-of-code' => 
        array (
          'pretty_version' => '4.0.0',
          'version' => '4.0.0.0',
          'reference' => '97ffee3bcfb5805568d6af7f0f893678fc076d2f',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/lines-of-code',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/object-enumerator' => 
        array (
          'pretty_version' => '7.0.0',
          'version' => '7.0.0.0',
          'reference' => '1effe8e9b8e068e9ae228e542d5d11b5d16db894',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/object-enumerator',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/object-reflector' => 
        array (
          'pretty_version' => '5.0.0',
          'version' => '5.0.0.0',
          'reference' => '4bfa827c969c98be1e527abd576533293c634f6a',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/object-reflector',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/recursion-context' => 
        array (
          'pretty_version' => '7.0.1',
          'version' => '7.0.1.0',
          'reference' => '0b01998a7d5b1f122911a66bebcb8d46f0c82d8c',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/recursion-context',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/type' => 
        array (
          'pretty_version' => '6.0.3',
          'version' => '6.0.3.0',
          'reference' => 'e549163b9760b8f71f191651d22acf32d56d6d4d',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/type',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'sebastian/version' => 
        array (
          'pretty_version' => '6.0.0',
          'version' => '6.0.0.0',
          'reference' => '3e6ccf7657d4f0a59200564b08cead899313b53c',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../sebastian/version',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'staabm/side-effects-detector' => 
        array (
          'pretty_version' => '1.0.5',
          'version' => '1.0.5.0',
          'reference' => 'd8334211a140ce329c13726d4a715adbddd0a163',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../staabm/side-effects-detector',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/console' => 
        array (
          'pretty_version' => 'v7.3.4',
          'version' => '7.3.4.0',
          'reference' => '2b9c5fafbac0399a20a2e82429e2bd735dcfb7db',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../symfony/console',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/deprecation-contracts' => 
        array (
          'pretty_version' => 'v3.6.0',
          'version' => '3.6.0.0',
          'reference' => '63afe740e99a13ba87ec199bb07bbdee937a5b62',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../symfony/deprecation-contracts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/finder' => 
        array (
          'pretty_version' => 'v7.3.2',
          'version' => '7.3.2.0',
          'reference' => '2a6614966ba1074fa93dae0bc804227422df4dfe',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../symfony/finder',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/polyfill-ctype' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => 'a3cc8b044a6ea513310cbd48ef7333b384945638',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../symfony/polyfill-ctype',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/polyfill-intl-grapheme' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => '380872130d3a5dd3ace2f4010d95125fde5d5c70',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../symfony/polyfill-intl-grapheme',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/polyfill-intl-normalizer' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => '3833d7255cc303546435cb650316bff708a1c75c',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../symfony/polyfill-intl-normalizer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/polyfill-mbstring' => 
        array (
          'pretty_version' => 'v1.33.0',
          'version' => '1.33.0.0',
          'reference' => '6d857f4d76bd4b343eac26d6b539585d2bc56493',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../symfony/polyfill-mbstring',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/process' => 
        array (
          'pretty_version' => 'v7.3.4',
          'version' => '7.3.4.0',
          'reference' => 'f24f8f316367b30810810d4eb30c543d7003ff3b',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../symfony/process',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/service-contracts' => 
        array (
          'pretty_version' => 'v3.6.0',
          'version' => '3.6.0.0',
          'reference' => 'f021b05a130d35510bd6b25fe9053c2a8a15d5d4',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../symfony/service-contracts',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'symfony/string' => 
        array (
          'pretty_version' => 'v7.3.4',
          'version' => '7.3.4.0',
          'reference' => 'f96476035142921000338bad71e5247fbc138872',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../symfony/string',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'ta-tikoma/phpunit-architecture-test' => 
        array (
          'pretty_version' => '0.8.5',
          'version' => '0.8.5.0',
          'reference' => 'cf6fb197b676ba716837c886baca842e4db29005',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../ta-tikoma/phpunit-architecture-test',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'theseer/tokenizer' => 
        array (
          'pretty_version' => '1.2.3',
          'version' => '1.2.3.0',
          'reference' => '737eda637ed5e28c3413cb1ebe8bb52cbf1ca7a2',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../theseer/tokenizer',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'tomasvotruba/type-coverage' => 
        array (
          'pretty_version' => '2.0.2',
          'version' => '2.0.2.0',
          'reference' => 'd033429580f2c18bda538fa44f2939236a990e0c',
          'type' => 'phpstan-extension',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../tomasvotruba/type-coverage',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
        'webmozart/assert' => 
        array (
          'pretty_version' => '1.11.0',
          'version' => '1.11.0.0',
          'reference' => '11cb2199493b2f8a3b53e7f19068fc6aac760991',
          'type' => 'library',
          'install_path' => '/mnt/develop/underpixels/parallite/parallite-php/vendor/composer/../webmozart/assert',
          'aliases' => 
          array (
          ),
          'dev_requirement' => true,
        ),
      ),
    ),
  ),
  'executedFilesHashes' => 
  array (
    'phar:///mnt/develop/underpixels/parallite/parallite-php/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/Attribute85.php' => '123dcd45f03f2463904087a66bfe2bc139760df0',
    'phar:///mnt/develop/underpixels/parallite/parallite-php/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/ReflectionAttribute.php' => '0b4b78277eb6545955d2ce5e09bff28f1f8052c8',
    'phar:///mnt/develop/underpixels/parallite/parallite-php/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/ReflectionIntersectionType.php' => 'a3e6299b87ee5d407dae7651758edfa11a74cb11',
    'phar:///mnt/develop/underpixels/parallite/parallite-php/vendor/phpstan/phpstan/phpstan.phar/stubs/runtime/ReflectionUnionType.php' => '1b349aa997a834faeafe05fa21bc31cae22bf2e2',
  ),
  'phpExtensions' => 
  array (
    0 => 'Core',
    1 => 'FFI',
    2 => 'PDO',
    3 => 'Phar',
    4 => 'Reflection',
    5 => 'SPL',
    6 => 'SimpleXML',
    7 => 'Zend OPcache',
    8 => 'bcmath',
    9 => 'bz2',
    10 => 'calendar',
    11 => 'ctype',
    12 => 'curl',
    13 => 'date',
    14 => 'dom',
    15 => 'exif',
    16 => 'fileinfo',
    17 => 'filter',
    18 => 'ftp',
    19 => 'gd',
    20 => 'gettext',
    21 => 'hash',
    22 => 'iconv',
    23 => 'igbinary',
    24 => 'imagick',
    25 => 'intl',
    26 => 'json',
    27 => 'libxml',
    28 => 'mbstring',
    29 => 'memcached',
    30 => 'msgpack',
    31 => 'mysqli',
    32 => 'mysqlnd',
    33 => 'openssl',
    34 => 'pcntl',
    35 => 'pcre',
    36 => 'pdo_mysql',
    37 => 'pdo_sqlite',
    38 => 'posix',
    39 => 'random',
    40 => 'readline',
    41 => 'redis',
    42 => 'session',
    43 => 'shmop',
    44 => 'soap',
    45 => 'sockets',
    46 => 'sodium',
    47 => 'sqlite3',
    48 => 'standard',
    49 => 'sysvmsg',
    50 => 'sysvsem',
    51 => 'sysvshm',
    52 => 'tokenizer',
    53 => 'xml',
    54 => 'xmlreader',
    55 => 'xmlwriter',
    56 => 'xsl',
    57 => 'zip',
    58 => 'zlib',
  ),
  'stubFiles' => 
  array (
  ),
  'level' => '9',
),
	'projectExtensionFiles' => array (
),
	'errorsCallback' => static function (): array { return array (
); },
	'locallyIgnoredErrorsCallback' => static function (): array { return array (
); },
	'linesToIgnore' => array (
),
	'unmatchedLineIgnores' => array (
),
	'collectedDataCallback' => static function (): array { return array (
  '/mnt/develop/underpixels/parallite/parallite-php/src/Installer.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\PossiblyPureFuncCallCollector' => 
    array (
      0 => 
      array (
        0 => 'exec',
        1 => 240,
      ),
      1 => 
      array (
        0 => 'array_map',
        1 => 263,
      ),
      2 => 
      array (
        0 => 'array_map',
        1 => 309,
      ),
    ),
    'PHPStan\\Rules\\DeadCode\\PossiblyPureMethodCallCollector' => 
    array (
      0 => 
      array (
        0 => 
        array (
          0 => 'ZipArchive',
        ),
        1 => 'extractTo',
        2 => 289,
      ),
      1 => 
      array (
        0 => 
        array (
          0 => 'ZipArchive',
        ),
        1 => 'close',
        2 => 290,
      ),
    ),
  ),
  '/mnt/develop/underpixels/parallite/parallite-php/src/ParalliteClient.php' => 
  array (
    'PHPStan\\Rules\\DeadCode\\PossiblyPureFuncCallCollector' => 
    array (
      0 => 
      array (
        0 => 'posix_kill',
        1 => 499,
      ),
    ),
  ),
); },
	'dependencies' => array (
  '/mnt/develop/underpixels/parallite/parallite-php/src/Installer.php' => 
  array (
    'fileHash' => '2b175ca9915797549ef1e4cff3cea3da25d6b426',
    'dependentFiles' => 
    array (
    ),
  ),
  '/mnt/develop/underpixels/parallite/parallite-php/src/ParalliteClient.php' => 
  array (
    'fileHash' => '42db4f0891676ce6f3ec6d6167dfaaccfef92e06',
    'dependentFiles' => 
    array (
    ),
  ),
  '/mnt/develop/underpixels/parallite/parallite-php/src/Support/parallite-worker.php' => 
  array (
    'fileHash' => '23e734981f68d34dcc52a5be4fcf4103938bbfa6',
    'dependentFiles' => 
    array (
    ),
  ),
),
	'exportedNodesCallback' => static function (): array { return array (
  '/mnt/develop/underpixels/parallite/parallite-php/src/Installer.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'Parallite\\Installer',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/**
 * Handles downloading and installing the Parallite binary from GitHub releases.
 */',
         'namespace' => 'Parallite',
         'uses' => 
        array (
          'runtimeexception' => 'RuntimeException',
          'ziparchive' => 'ZipArchive',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => true,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'install',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Install the Parallite binary for the current platform.
     *
     * @param bool $force Force reinstall even if binary exists
     */',
             'namespace' => 'Parallite',
             'uses' => 
            array (
              'runtimeexception' => 'RuntimeException',
              'ziparchive' => 'ZipArchive',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => true,
           'returnType' => 'void',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'force',
               'type' => 'bool',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'update',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Update the Parallite binary to the latest version.
     */',
             'namespace' => 'Parallite',
             'uses' => 
            array (
              'runtimeexception' => 'RuntimeException',
              'ziparchive' => 'ZipArchive',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => true,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'getInstalledVersion',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Get the current installed version of Parallite.
     */',
             'namespace' => 'Parallite',
             'uses' => 
            array (
              'runtimeexception' => 'RuntimeException',
              'ziparchive' => 'ZipArchive',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => true,
           'returnType' => '?string',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'checkForUpdates',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Check if a newer version is available.
     */',
             'namespace' => 'Parallite',
             'uses' => 
            array (
              'runtimeexception' => 'RuntimeException',
              'ziparchive' => 'ZipArchive',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => true,
           'returnType' => '?string',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/mnt/develop/underpixels/parallite/parallite-php/src/ParalliteClient.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedClassNode::__set_state(array(
       'name' => 'Parallite\\ParalliteClient',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/**
 * Parallite Client - Standalone PHP Client for Parallite Daemon
 * 
 * This class provides a simple interface to communicate with Parallite daemon
 * and execute PHP closures in parallel.
 * 
 * Usage:
 * ```php
 * use Parallite\\ParalliteClient;
 * 
 * // Option 1: Automatic daemon management (recommended)
 * $client = new ParalliteClient(autoManageDaemon: true);
 * 
 * // Option 2: Manual daemon management (you start daemon yourself)
 * $client = new ParalliteClient(\'/tmp/parallite-custom.sock\', autoManageDaemon: false);
 * 
 * // Submit tasks
 * $future1 = $client->async(fn() => sleep(1) && \'Task 1\');
 * $future2 = $client->async(fn() => sleep(2) && \'Task 2\');
 * 
 * // Await results
 * $result1 = $client->await($future1);
 * $result2 = $client->await($future2);
 * 
 * // Daemon is automatically stopped on script end if autoManageDaemon=true
 * ```
 * 
 * Required dependencies:
 * - PHP 8.2+
 * - opis/closure
 * - ext-sockets
 * 
 * Configuration (parallite.json in project root):
 * - php_includes: Files loaded by worker processes
 * - go_overrides: Daemon configuration (timeout, workers, etc)
 */',
         'namespace' => 'Parallite',
         'uses' => 
        array (
          'closure' => 'Closure',
          'runtimeexception' => 'RuntimeException',
        ),
         'constUses' => 
        array (
        ),
      )),
       'abstract' => false,
       'final' => false,
       'extends' => NULL,
       'implements' => 
      array (
      ),
       'usedTraits' => 
      array (
      ),
       'traitUseAdaptations' => 
      array (
      ),
       'statements' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => '__construct',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Create a new Parallite client
     * 
     * @param string $socketPath Path to socket (Unix: /tmp/file.sock, Windows: \\\\.\\pipe\\name)
     * @param bool $autoManageDaemon If true, automatically starts/stops daemon
     * @param string|null $projectRoot Project root directory (auto-detected if null)
     */',
             'namespace' => 'Parallite',
             'uses' => 
            array (
              'closure' => 'Closure',
              'runtimeexception' => 'RuntimeException',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => NULL,
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'socketPath',
               'type' => 'string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
            )),
            1 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'autoManageDaemon',
               'type' => 'bool',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
            )),
            2 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'projectRoot',
               'type' => '?string',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        1 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'async',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Submit a task for parallel execution
     * 
     * This method sends the task to Parallite daemon and returns a future
     * that can be awaited later. The socket is kept open to allow parallel execution.
     * 
     * @param Closure $closure The closure to execute
     * @return array{socket: \\Socket, task_id: string} Future containing socket and task_id
     * @throws RuntimeException If connection or send fails
     */',
             'namespace' => 'Parallite',
             'uses' => 
            array (
              'closure' => 'Closure',
              'runtimeexception' => 'RuntimeException',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'closure',
               'type' => 'Closure',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        2 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'await',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Await the result of a previously submitted task
     * 
     * This method reads the response from the open socket and returns the result.
     * The socket is automatically closed after reading.
     * 
     * @param array{socket: \\Socket|null, task_id: string}|null $future The future returned by async()
     * @return mixed The result of the task execution
     * @throws RuntimeException If reading fails or task failed
     */',
             'namespace' => 'Parallite',
             'uses' => 
            array (
              'closure' => 'Closure',
              'runtimeexception' => 'RuntimeException',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'mixed',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'future',
               'type' => '?array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => true,
               'attributes' => 
              array (
              ),
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        3 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'awaitAll',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Submit multiple tasks and await all results
     * 
     * This is a convenience method that combines async() and await()
     * for multiple tasks, similar to Promise.all() in JavaScript.
     * 
     * @param array<Closure> $closures Array of closures to execute
     * @return array<mixed> Array of results in the same order
     */',
             'namespace' => 'Parallite',
             'uses' => 
            array (
              'closure' => 'Closure',
              'runtimeexception' => 'RuntimeException',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'array',
           'parameters' => 
          array (
            0 => 
            \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
               'name' => 'closures',
               'type' => 'array',
               'byRef' => false,
               'variadic' => false,
               'hasDefault' => false,
               'attributes' => 
              array (
              ),
            )),
          ),
           'attributes' => 
          array (
          ),
        )),
        4 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'getDefaultSocketPath',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Get default socket path for the current platform
     * 
     * @return string Socket path (Unix socket or Windows named pipe)
     */',
             'namespace' => 'Parallite',
             'uses' => 
            array (
              'closure' => 'Closure',
              'runtimeexception' => 'RuntimeException',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => true,
           'returnType' => 'string',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
        5 => 
        \PHPStan\Dependency\ExportedNode\ExportedMethodNode::__set_state(array(
           'name' => 'stopDaemon',
           'phpDoc' => 
          \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
             'phpDocString' => '/**
     * Stop the daemon process
     * 
     * @return void
     */',
             'namespace' => 'Parallite',
             'uses' => 
            array (
              'closure' => 'Closure',
              'runtimeexception' => 'RuntimeException',
            ),
             'constUses' => 
            array (
            ),
          )),
           'byRef' => false,
           'public' => true,
           'private' => false,
           'abstract' => false,
           'final' => false,
           'static' => false,
           'returnType' => 'void',
           'parameters' => 
          array (
          ),
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
  '/mnt/develop/underpixels/parallite/parallite-php/src/Support/parallite-worker.php' => 
  array (
    0 => 
    \PHPStan\Dependency\ExportedNode\ExportedFunctionNode::__set_state(array(
       'name' => 'findProjectRoot',
       'phpDoc' => 
      \PHPStan\Dependency\ExportedNode\ExportedPhpDocNode::__set_state(array(
         'phpDocString' => '/**
 * Parallite PHP Worker
 *
 * This worker process is spawned by the Go daemon to execute PHP closures.
 */',
         'namespace' => NULL,
         'uses' => 
        array (
        ),
         'constUses' => 
        array (
        ),
      )),
       'byRef' => false,
       'returnType' => '?string',
       'parameters' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
           'name' => 'startDir',
           'type' => 'string',
           'byRef' => false,
           'variadic' => false,
           'hasDefault' => false,
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
    1 => 
    \PHPStan\Dependency\ExportedNode\ExportedFunctionNode::__set_state(array(
       'name' => 'workerLog',
       'phpDoc' => NULL,
       'byRef' => false,
       'returnType' => 'void',
       'parameters' => 
      array (
        0 => 
        \PHPStan\Dependency\ExportedNode\ExportedParameterNode::__set_state(array(
           'name' => 'message',
           'type' => 'string',
           'byRef' => false,
           'variadic' => false,
           'hasDefault' => false,
           'attributes' => 
          array (
          ),
        )),
      ),
       'attributes' => 
      array (
      ),
    )),
  ),
); },
];
