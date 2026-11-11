package dashboard

import (
	"embed"
	"io/fs"
	"mime"
	"net/http"
	"path/filepath"

	"github.com/gin-gonic/gin"
)

//go:embed embedded/index.html embedded/assets
var uiFiles embed.FS

func ServeIndex(c *gin.Context) {
	data, err := fs.ReadFile(uiFiles, "embedded/index.html")
	if err != nil {
		c.AbortWithStatus(http.StatusInternalServerError)
		return
	}
	c.Header("Cache-Control", "no-store")
	c.Header("X-Content-Type-Options", "nosniff")
	c.Header("Referrer-Policy", "strict-origin-when-cross-origin")
	c.Header("Permissions-Policy", "camera=(), microphone=(), geolocation=()")
	c.Header("Cross-Origin-Opener-Policy", "same-origin")
	c.Header("Content-Security-Policy", "default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'")
	c.Data(http.StatusOK, "text/html; charset=utf-8", data)
}

func ServeAsset(c *gin.Context) {
	name := filepath.Base(c.Param("name"))
	if name != "app.css" && name != "alpine.min.js" && name != "dashboard.js" {
		c.AbortWithStatus(http.StatusNotFound)
		return
	}
	data, err := fs.ReadFile(uiFiles, "embedded/assets/"+name)
	if err != nil {
		c.AbortWithStatus(http.StatusNotFound)
		return
	}
	contentType := mime.TypeByExtension(filepath.Ext(name))
	if contentType == "" {
		contentType = "application/octet-stream"
	}
	c.Header("Cache-Control", "public, max-age=3600")
	c.Header("X-Content-Type-Options", "nosniff")
	c.Data(http.StatusOK, contentType, data)
}
