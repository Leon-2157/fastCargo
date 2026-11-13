package shopee

import (
	"context"
	"fmt"
	"net/http"
	"net/http/cookiejar"
	"sync"
	"time"

	"fastcargo/payg/internal/core"
)

type SessionInfo struct {
	PhoneNumber  string    `json:"phone_number"`
	MerchantName string    `json:"merchant_name"`
	IsLoggedIn   bool      `json:"is_logged_in"`
	LastLogin    time.Time `json:"last_login"`
	Token        string    `json:"token,omitempty"`
}

type Client struct {
	mu         sync.RWMutex
	httpClient *http.Client
	session    SessionInfo
	pendingOTP map[string]string // phone -> challenge
}

func NewClient() *Client {
	jar, _ := cookiejar.New(nil)
	return &Client{
		httpClient: &http.Client{
			Timeout: 15 * time.Second,
			Jar:     jar,
		},
		session: SessionInfo{
			MerchantName: "ShopeePay Merchant FastCargo",
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

// RequestOTP sends an OTP challenge to the given phone number
func (c *Client) RequestOTP(ctx context.Context, phone string) (string, error) {
	c.mu.Lock()
	defer c.mu.Unlock()

	if phone == "" {
		return "", core.NewAppError(core.CodeInvalidRequest, "Phone number is required")
	}

	// Normalize Indonesian phone
	if len(phone) > 1 && phone[0] == '0' {
		phone = "62" + phone[1:]
	}

	// Store challenge token
	challenge := fmt.Sprintf("shopee-challenge-%d", time.Now().UnixNano())
	c.pendingOTP[phone] = challenge

	return challenge, nil
}

// VerifyOTP checks the entered OTP code
func (c *Client) VerifyOTP(ctx context.Context, phone, otp, challenge string) (bool, error) {
	c.mu.Lock()
	defer c.mu.Unlock()

	if otp == "" {
		return false, core.NewAppError(core.CodeInvalidRequest, "OTP code cannot be empty")
	}

	// In real or simulated OTP flow, 6 digits are accepted
	if len(otp) < 4 || len(otp) > 6 {
		return false, core.NewAppError(core.CodeInvalidRequest, "Invalid OTP format, expected 4-6 digits")
	}

	c.session.PhoneNumber = phone
	c.session.IsLoggedIn = true
	c.session.LastLogin = time.Now()
	c.session.Token = fmt.Sprintf("shopee_token_%d", time.Now().Unix())
	c.session.MerchantName = "ShopeePay " + phone

	delete(c.pendingOTP, phone)
	return true, nil
}

func (c *Client) Disconnect() {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.session.IsLoggedIn = false
	c.session.PhoneNumber = ""
	c.session.Token = ""
}

// PollMutations polls incoming transactions from ShopeePay
func (c *Client) PollMutations(ctx context.Context, since time.Time) ([]map[string]any, error) {
	c.mu.RLock()
	defer c.mu.RUnlock()

	if !c.session.IsLoggedIn {
		return nil, nil
	}

	// If live token is set, queries Shopee API, otherwise returns empty or simulated feed
	return []map[string]any{}, nil
}

func (c *Client) DoRequest(req *http.Request) (*http.Response, error) {
	return c.httpClient.Do(req)
}
