<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Alias\ArrayPushFixer;
use PhpCsFixer\Fixer\Phpdoc\NoEmptyPhpdocFixer;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use PhpCsFixer\Fixer\Alias\NoAliasFunctionsFixer;
use PhpCsFixer\Fixer\Comment\NoEmptyCommentFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use PhpCsFixer\Fixer\Casing\MagicMethodCasingFixer;
use PhpCsFixer\Fixer\ControlStructure\IncludeFixer;
use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use SlevomatCodingStandard\Sniffs\PHP\TypeCastSniff;
use PhpCsFixer\Fixer\Casing\MagicConstantCasingFixer;
use PhpCsFixer\Fixer\ClassNotation\SelfAccessorFixer;
use PhpCsFixer\Fixer\Semicolon\NoEmptyStatementFixer;
use PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer;
use SlevomatCodingStandard\Sniffs\PHP\ShortListSniff;
use PhpCsFixer\Fixer\Casing\NativeFunctionCasingFixer;
use PhpCsFixer\Fixer\CastNotation\NoShortBoolCastFixer;
use PhpCsFixer\Fixer\Phpdoc\AlignMultilineCommentFixer;
use PhpCsFixer\Fixer\FunctionNotation\StaticLambdaFixer;
use PhpCsFixer\Fixer\LanguageConstruct\DirConstantFixer;
use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;
use PhpCsFixer\Fixer\ControlStructure\NoUselessElseFixer;
use PhpCsFixer\Fixer\Phpdoc\NoBlankLinesAfterPhpdocFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\ReturnNotation\NoUselessReturnFixer;
use PhpCsFixer\Fixer\Import\FullyQualifiedStrictTypesFixer;
use PhpCsFixer\Fixer\Operator\TernaryToNullCoalescingFixer;
use SlevomatCodingStandard\Sniffs\PHP\ReferenceSpacingSniff;
use SlevomatCodingStandard\Sniffs\PHP\UselessSemicolonSniff;
use PhpCsFixer\Fixer\FunctionNotation\UseArrowFunctionsFixer;
use SlevomatCodingStandard\Sniffs\Classes\MethodSpacingSniff;
use PhpCsFixer\Fixer\ControlStructure\SimplifiedIfReturnFixer;
use SlevomatCodingStandard\Sniffs\Classes\ClassStructureSniff;
use SlevomatCodingStandard\Sniffs\PHP\UselessParenthesesSniff;
use SlevomatCodingStandard\Sniffs\Classes\ConstantSpacingSniff;
use SlevomatCodingStandard\Sniffs\Classes\PropertySpacingSniff;
use SlevomatCodingStandard\Sniffs\Commenting\EmptyCommentSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\UselessAliasSniff;
use PhpCsFixer\Fixer\FunctionNotation\CombineNestedDirnameFixer;
use PhpCsFixer\Fixer\Comment\MultilineCommentOpeningClosingFixer;
use PhpCsFixer\Fixer\ControlStructure\NoUnneededCurlyBracesFixer;
use PhpCsFixer\Fixer\ControlStructure\SwitchContinueToBreakFixer;
use SlevomatCodingStandard\Sniffs\Arrays\TrailingArrayCommaSniff;
use SlevomatCodingStandard\Sniffs\Classes\ClassMemberSpacingSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DeclareStrictTypesSniff;
use PhpCsFixer\Fixer\Casing\NativeFunctionTypeDeclarationCasingFixer;
use PhpCsFixer\Fixer\ClassNotation\NoNullPropertyInitializationFixer;
use SlevomatCodingStandard\Sniffs\TypeHints\UnionTypeHintFormatSniff;
use SlevomatCodingStandard\Sniffs\Classes\ClassConstantVisibilitySniff;
use SlevomatCodingStandard\Sniffs\Namespaces\NamespaceDeclarationSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\UseFromSameNamespaceSniff;
use SlevomatCodingStandard\Sniffs\Operators\SpreadOperatorSpacingSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\ReturnTypeHintSpacingSniff;
use SlevomatCodingStandard\Sniffs\Classes\ModernClassNameReferenceSniff;
use SlevomatCodingStandard\Sniffs\Operators\NegationOperatorSpacingSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\UselessConstantTypeHintSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\ParameterTypeHintSpacingSniff;
use SlevomatCodingStandard\Sniffs\ControlStructures\NewWithParenthesesSniff;
use SlevomatCodingStandard\Sniffs\Functions\RequireTrailingCommaInCallSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\RequireOneNamespaceInFileSniff;
use SlevomatCodingStandard\Sniffs\Numbers\RequireNumericLiteralSeparatorSniff;
use Symplify\CodingStandard\Fixer\Spacing\StandaloneLinePromotedPropertyFixer;
use SlevomatCodingStandard\Sniffs\Classes\DisallowMultiConstantDefinitionSniff;
use SlevomatCodingStandard\Sniffs\Classes\DisallowMultiPropertyDefinitionSniff;
use SlevomatCodingStandard\Sniffs\ControlStructures\AssignmentInConditionSniff;
use SlevomatCodingStandard\Sniffs\Namespaces\UseDoesNotStartWithBackslashSniff;
use Symplify\CodingStandard\Fixer\Commenting\ParamReturnAndVarTagMalformsFixer;
use SlevomatCodingStandard\Sniffs\ControlStructures\DisallowYodaComparisonSniff;
use SlevomatCodingStandard\Sniffs\ControlStructures\UselessTernaryOperatorSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\NullableTypeForNullDefaultValueSniff;
use SlevomatCodingStandard\Sniffs\Classes\RequireConstructorPropertyPromotionSniff;
use SlevomatCodingStandard\Sniffs\Functions\RequireTrailingCommaInDeclarationSniff;
use SlevomatCodingStandard\Sniffs\Operators\RequireCombinedAssignmentOperatorSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\NamingConventions\ValidVariableNameSniff;
use SlevomatCodingStandard\Sniffs\ControlStructures\RequireShortTernaryOperatorSniff;
use SlevomatCodingStandard\Sniffs\ControlStructures\UselessIfConditionWithReturnSniff;
use SlevomatCodingStandard\Sniffs\ControlStructures\RequireNullSafeObjectOperatorSniff;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $ecsConfig->sets([SetList::PSR_12]);

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // https://github.com/symplify/coding-standard/blob/main/docs/rules_overview.md
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // https://github.com/symplify/coding-standard/blob/main/docs/rules_overview.md#paramreturnandvartagmalformsfixer
    $ecsConfig->rule(ParamReturnAndVarTagMalformsFixer::class);

    // https://github.com/symplify/coding-standard/blob/main/docs/rules_overview.md#standalonelinepromotedpropertyfixer
    $ecsConfig->rule(StandaloneLinePromotedPropertyFixer::class);

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // https://mlocati.github.io/php-cs-fixer-configurator/
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    //https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_empty_phpdoc
    $ecsConfig->rule(NoEmptyPhpdocFixer::class);

    //https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_empty_comment
    $ecsConfig->rule(NoEmptyCommentFixer::class);

    $ecsConfig->rule(DeclareStrictTypesFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:array_syntax
    $ecsConfig->ruleWithConfiguration(ArraySyntaxFixer::class, [
        'syntax' => 'short',
    ]);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:static_lambda
    $ecsConfig->rule(StaticLambdaFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:ordered_imports
    $ecsConfig->ruleWithConfiguration(OrderedImportsFixer::class, [
        'imports_order' => ['class', 'function', 'const'],
        'sort_algorithm' => 'length',
    ]);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_unused_imports
    $ecsConfig->rule(NoUnusedImportsFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_empty_statement
    $ecsConfig->rule(NoEmptyStatementFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:binary_operator_spaces
    $ecsConfig->ruleWithConfiguration(BinaryOperatorSpacesFixer::class, [
        'operators' => ['|' => null],
    ]);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_unneeded_curly_braces
    $ecsConfig->rule(NoUnneededCurlyBracesFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_superfluous_phpdoc_tags
    $ecsConfig->rule(NoSuperfluousPhpdocTagsFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:fully_qualified_strict_types
    $ecsConfig->rule(FullyQualifiedStrictTypesFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:align_multiline_comment
    $ecsConfig->rule(AlignMultilineCommentFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:array_push
    $ecsConfig->rule(ArrayPushFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:combine_nested_dirname
    $ecsConfig->rule(CombineNestedDirnameFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:dir_constant
    $ecsConfig->rule(DirConstantFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:include
    $ecsConfig->rule(IncludeFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:magic_constant_casing
    $ecsConfig->rule(MagicConstantCasingFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:magic_method_casing
    $ecsConfig->rule(MagicMethodCasingFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:multiline_comment_opening_closing
    $ecsConfig->rule(MultilineCommentOpeningClosingFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:native_function_casing
    $ecsConfig->rule(NativeFunctionCasingFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:native_function_type_declaration_casing
    $ecsConfig->rule(NativeFunctionTypeDeclarationCasingFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_alias_functions
    $ecsConfig->rule(NoAliasFunctionsFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_blank_lines_after_phpdoc
    $ecsConfig->rule(NoBlankLinesAfterPhpdocFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_null_property_initialization
    $ecsConfig->rule(NoNullPropertyInitializationFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_short_bool_cast
    $ecsConfig->rule(NoShortBoolCastFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_useless_else
    $ecsConfig->rule(NoUselessElseFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:no_useless_return
    $ecsConfig->rule(NoUselessReturnFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:self_accessor
    $ecsConfig->rule(SelfAccessorFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:simplified_if_return
    $ecsConfig->rule(SimplifiedIfReturnFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:single_quote
    $ecsConfig->rule(SingleQuoteFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:switch_continue_to_break
    $ecsConfig->rule(SwitchContinueToBreakFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:ternary_to_null_coalescing
    $ecsConfig->rule(TernaryToNullCoalescingFixer::class);

    // https://mlocati.github.io/php-cs-fixer-configurator/#version:3.1|fixer:use_arrow_functions
    $ecsConfig->rule(UseArrowFunctionsFixer::class);

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // https://github.com/slevomat/coding-standard#sniffs-included-in-this-standard
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardphpuselesssemicolon-
    $ecsConfig->rule(UselessSemicolonSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardphpuselessparentheses-
    $ecsConfig->rule(UselessParenthesesSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardtypehintsdeclarestricttypes-
    $ecsConfig->ruleWithConfiguration(DeclareStrictTypesSniff::class, [
        'spacesCountAroundEqualsSign' => 0,
    ]);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardcontrolstructuresrequireshortternaryoperator-
    $ecsConfig->rule(RequireShortTernaryOperatorSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardoperatorsrequirecombinedassignmentoperator-
    $ecsConfig->rule(RequireCombinedAssignmentOperatorSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardtypehintsuselessconstanttypehint-
    $ecsConfig->rule(UselessConstantTypeHintSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardtypehintsuniontypehintformat-
    $ecsConfig->ruleWithConfiguration(UnionTypeHintFormatSniff::class, [
        'withSpaces' => 'no',
        'nullPosition' => 'last',
        'shortNullable' => 'yes',
    ]);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardclassesclassstructure-
    $ecsConfig->ruleWithConfiguration(ClassStructureSniff::class, [
        'groups' => ['uses', 'enum cases', 'constants', 'properties', 'static properties', 'methods', 'all public methods',
            'all protected methods', 'all private methods', 'static methods', 'final methods', 'abstract methods'],
    ]);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardclassesrequireconstructorpropertypromotion-
    $ecsConfig->rule(RequireConstructorPropertyPromotionSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardcontrolstructuresassignmentincondition
    $ecsConfig->rule(AssignmentInConditionSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardnamespacesusefromsamenamespace-
    $ecsConfig->rule(UseFromSameNamespaceSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardnamespacesuselessalias-
    $ecsConfig->rule(UselessAliasSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardarraystrailingarraycomma-
    $ecsConfig->rule(TrailingArrayCommaSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardclassesclassmemberspacing-
    $ecsConfig->rule(ClassMemberSpacingSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardclassesconstantspacing-
    $ecsConfig->rule(ConstantSpacingSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardclassesdisallowmulticonstantdefinition-
    $ecsConfig->rule(DisallowMultiConstantDefinitionSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardclassesdisallowmultipropertydefinition-
    $ecsConfig->rule(DisallowMultiPropertyDefinitionSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardclassesmethodspacing-
    $ecsConfig->rule(MethodSpacingSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardclassesmodernclassnamereference-
    $ecsConfig->rule(ModernClassNameReferenceSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardclassespropertyspacing-
    $ecsConfig->rule(PropertySpacingSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardcontrolstructuresnewwithparentheses-
    $ecsConfig->rule(NewWithParenthesesSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardcontrolstructuresrequirenullsafeobjectoperator-
    $ecsConfig->rule(RequireNullSafeObjectOperatorSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardcontrolstructuresdisallowyodacomparisonrequireyodacomparison-
    $ecsConfig->rule(DisallowYodaComparisonSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardfunctionsrequiretrailingcommaincall-
    $ecsConfig->rule(RequireTrailingCommaInCallSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardfunctionsrequiretrailingcommaindeclaration-
    $ecsConfig->rule(RequireTrailingCommaInDeclarationSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardnamespacesrequireonenamespaceinfile
    $ecsConfig->rule(RequireOneNamespaceInFileSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardnamespacesnamespacedeclaration-
    $ecsConfig->rule(NamespaceDeclarationSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardnumbersrequirenumericliteralseparator
    $ecsConfig->ruleWithConfiguration(RequireNumericLiteralSeparatorSniff::class, [
        'minDigitsAfterDecimalPoint' => 6,
        'minDigitsBeforeDecimalPoint' => 6,
    ]);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardphpreferencespacing-
    $ecsConfig->rule(ReferenceSpacingSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardoperatorsnegationoperatorspacing-
    $ecsConfig->rule(NegationOperatorSpacingSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardoperatorsspreadoperatorspacing-
    $ecsConfig->rule(SpreadOperatorSpacingSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardphpshortlist-
    $ecsConfig->rule(ShortListSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardphptypecast-
    $ecsConfig->rule(TypeCastSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardclassesclassconstantvisibility-
    $ecsConfig->rule(ClassConstantVisibilitySniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardtypehintsreturntypehintspacing-
    $ecsConfig->rule(ReturnTypeHintSpacingSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardtypehintsnullabletypefornulldefaultvalue-
    $ecsConfig->rule(NullableTypeForNullDefaultValueSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardtypehintsparametertypehintspacing-
    $ecsConfig->rule(ParameterTypeHintSpacingSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardtypehintspropertytypehint-
    $ecsConfig->rule(PropertyTypeHintSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardnamespacesusedoesnotstartwithbackslash-
    $ecsConfig->rule(UseDoesNotStartWithBackslashSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardcommentingemptycomment-
    $ecsConfig->rule(EmptyCommentSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardcontrolstructuresuselessifconditionwithreturn-
    $ecsConfig->rule(UselessIfConditionWithReturnSniff::class);

    // https://github.com/slevomat/coding-standard#slevomatcodingstandardcontrolstructuresuselessternaryoperator-
    $ecsConfig->rule(UselessTernaryOperatorSniff::class);

    $ecsConfig->rule(ValidVariableNameSniff::class);
};
