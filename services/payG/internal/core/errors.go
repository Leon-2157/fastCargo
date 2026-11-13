package core

import "fmt"

type ErrorCode string

const (
	CodeInternalError        ErrorCode = "INTERNAL_ERROR"
	CodeInvalidRequest       ErrorCode = "INVALID_REQUEST"
	CodeTransactionNotFound  ErrorCode = "TRANSACTION_NOT_FOUND"
	CodeEngineNotConfigured  ErrorCode = "ENGINE_NOT_CONFIGURED"
	CodeAmountPoolExhausted  ErrorCode = "AMOUNT_POOL_EXHAUSTED"
	CodeQRISParseError       ErrorCode = "QRIS_PARSE_ERROR"
	CodeAuthFailed           ErrorCode = "AUTH_FAILED"
	CodeOtpRequired          ErrorCode = "OTP_REQUIRED"
)

type AppError struct {
	Code    ErrorCode      `json:"code"`
	Message string         `json:"message"`
	Details map[string]any `json:"details,omitempty"`
}

func (e *AppError) Error() string {
	return fmt.Sprintf("[%s] %s", e.Code, e.Message)
}

func NewAppError(code ErrorCode, message string) *AppError {
	return &AppError{
		Code:    code,
		Message: message,
	}
}

func NewAppErrorf(code ErrorCode, format string, args ...any) *AppError {
	return &AppError{
		Code:    code,
		Message: fmt.Sprintf(format, args...),
	}
}
