<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Catalogue of HMRC error codes we surface to instructors with friendly copy.
 *
 * The list grows phase by phase; unknown codes are caught by `default()` and
 * shown as a generic message with the raw code attached for support triage.
 */
enum HmrcErrorCode: string
{
    case MatchingResourceNotFound = 'MATCHING_RESOURCE_NOT_FOUND';
    case RuleDuplicateSubmission = 'RULE_DUPLICATE_SUBMISSION';
    case RuleBusinessValidationFailure = 'RULE_BUSINESS_VALIDATION_FAILURE';
    case RuleObligationFulfilled = 'RULE_OBLIGATION_FULFILLED';
    case InvalidNino = 'INVALID_NINO';
    case InvalidUtr = 'INVALID_UTR';
    case ClientOrAgentNotAuthorised = 'CLIENT_OR_AGENT_NOT_AUTHORISED';
    case InvalidScope = 'INVALID_SCOPE';
    case InvalidRequest = 'INVALID_REQUEST';
    case RuleIncorrectGovTestScenario = 'RULE_INCORRECT_GOV_TEST_SCENARIO';
    case RuleNotSignedUpToMtd = 'RULE_NOT_SIGNED_UP_TO_MTD';
    case InvalidFraudHeader = 'INVALID_FRAUD_HEADER';

    public function userMessage(): string
    {
        return match ($this) {
            self::MatchingResourceNotFound => 'HMRC could not find the resource you asked for. Double-check the period or business and try again.',
            self::RuleDuplicateSubmission => 'A submission for this period has already been received by HMRC. Use the amend flow if you need to correct it.',
            self::RuleBusinessValidationFailure => 'HMRC rejected the submission because of a business-rule mismatch. Check the figures and try again.',
            self::RuleObligationFulfilled => 'This obligation has already been fulfilled at HMRC.',
            self::InvalidNino => 'The National Insurance number on file is not valid. Update your tax profile and reconnect.',
            self::InvalidUtr => 'The UTR on file is not valid. Update your tax profile and reconnect.',
            self::ClientOrAgentNotAuthorised => 'HMRC says you are not authorised for this action. Reconnect to grant the required permissions.',
            self::InvalidScope => 'Your HMRC connection is missing a required permission. Reconnect to grant it.',
            self::InvalidRequest => 'HMRC rejected the request as malformed. Please contact support — this is a bug we need to fix.',
            self::RuleIncorrectGovTestScenario => 'The HMRC sandbox test scenario is wrong for this call. Operations team — check the Gov-Test-Scenario header.',
            self::RuleNotSignedUpToMtd => 'You are not signed up for Making Tax Digital yet. Sign up on gov.uk before using this feature.',
            self::InvalidFraudHeader => 'A fraud-prevention header was rejected by HMRC. Operations team — check the validator.',
        };
    }

    public function isRetryable(): bool
    {
        return match ($this) {
            self::InvalidRequest, self::RuleIncorrectGovTestScenario, self::InvalidFraudHeader => false,
            default => false,
        };
    }

    public static function tryFromString(?string $code): ?self
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::tryFrom($code);
    }

    public static function default(): string
    {
        return 'HMRC returned an unexpected error. Please try again or contact support if it persists.';
    }
}
