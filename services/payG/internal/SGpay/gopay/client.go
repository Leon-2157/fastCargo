package gopay

import (
	"context"
	"fmt"
	"net/http"
	"sync"
	"time"

	"fastcargo/payg/internal/core"
)

type SessionInfo struct {
	PhoneNumber  string    `json:"phone_number"`
	MerchantName string    `json:"merchant_name"`
	IsLoggedIn   bool      `json:"is_logged_in"`
	LastLogin    time.Time `json:"last_login"`
	AccessToken  string    `json:"access_token,omitempty"`
}

type Client struct {
	mu         sync.RWMutex
	httpClient *http.Client
	session    SessionInfo
	pendingOTP map[string]string // phone -> otp_token
}

func NewClient() *Client {
	return &Client{
		httpClient: &http.Client{Timeout: 15 * time.Second},
		session: SessionInfo{
			MerchantName: "GoBiz Merchant FastCargo",
			IsLoggedIn:   false,
		},
		pendingOTP: make(map[string]string),
	}
}

func (c *Client) GetSession() SessionInfo {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return c.session
}

func (c *Client) SetSession(s SessionInfo) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.session = s
}

func (c *Client) RequestOTP(ctx context.Context, phone string) (string, error) {
	c.mu.Lock()
	defer c.mu.Unlock()

	if phone == "" {
		return "", core.NewAppError(core.CodeInvalidRequest, "Phone number is required")
	}

	if len(phone) > 1 && phone[0] == '0' {
		phone = "62" + phone[1:]
	}

	token := fmt.Sprintf("gopay-otp-token-%d", time.Now().UnixNano())
	c.pendingOTP[phone] = token
	return token, nil
}

func (c *Client) VerifyOTP(ctx context.Context, phone, otp, otpToken string) (bool, error) {
	c.mu.Lock()
	defer c.mu.Unlock()

	if otp == "" {
		return false, core.NewAppError(core.CodeInvalidRequest, "OTP code is required")
	}

	c.session.PhoneNumber = phone
	c.session.IsLoggedIn = true
	c.session.LastLogin = time.Now()
	c.session.AccessToken = fmt.Sprintf("gopay_token_%d", time.Now().Unix())
	c.session.MerchantName = "GoPay Merchant " + phone

	delete(c.pendingOTP, phone)
	return true, nil
}

func (c *Client) Disconnect() {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.session.IsLoggedIn = false
	c.session.PhoneNumber = ""
	c.session.AccessToken = ""
}

func (c *Client) PollMutations(ctx context.Context, since time.Time) ([]map[string]any, error) {
	c.mu.RLock()
	defer c.mu.RUnlock()

	if !c.session.IsLoggedIn {
		return nil, nil
	}

	return []map[string]any{}, nil
}
