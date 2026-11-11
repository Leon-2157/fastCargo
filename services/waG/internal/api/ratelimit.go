package api

import (
	"net/http"
	"sync"
	"time"

	"github.com/gin-gonic/gin"
)

type rateBucket struct {
	started time.Time
	count   int
	window  time.Duration
}

type ipRateLimiter struct {
	mu      sync.Mutex
	buckets map[string]rateBucket
}

var requestLimiter = ipRateLimiter{buckets: make(map[string]rateBucket)}

func rateLimit(scope string, max int, window time.Duration) gin.HandlerFunc {
	return func(c *gin.Context) {
		host := c.ClientIP()
		if !requestLimiter.allow(scope+":"+host, max, window, time.Now()) {
			c.AbortWithStatusJSON(http.StatusTooManyRequests, gin.H{"error": "batas permintaan tercapai; coba lagi nanti"})
			return
		}
		c.Next()
	}
}

func (l *ipRateLimiter) allow(key string, max int, window time.Duration, now time.Time) bool {
	l.mu.Lock()
	defer l.mu.Unlock()
	if len(l.buckets) >= 10000 {
		for k, bucket := range l.buckets {
			if now.Sub(bucket.started) >= bucket.window {
				delete(l.buckets, k)
			}
		}
		if len(l.buckets) >= 10000 {
			return false
		}
	}
	bucket, ok := l.buckets[key]
	if !ok || now.Sub(bucket.started) >= window {
		l.buckets[key] = rateBucket{started: now, count: 1, window: window}
		return true
	}
	if bucket.count >= max {
		return false
	}
	bucket.count++
	l.buckets[key] = bucket
	return true
}

func limitRequestBody(maxBytes int64) gin.HandlerFunc {
	return func(c *gin.Context) {
		if c.Request.Body != nil {
			c.Request.Body = http.MaxBytesReader(c.Writer, c.Request.Body, maxBytes)
		}
		c.Next()
	}
}
