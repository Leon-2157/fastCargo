package simulator

import (
	"fmt"
	"strings"

	"rsc.io/qr"
)

// GenerateQRSvg converts text payload into a scalable SVG string
func GenerateQRSvg(content string, sizePx int) (string, error) {
	if sizePx <= 0 {
		sizePx = 280
	}

	code, err := qr.Encode(content, qr.M)
	if err != nil {
		return "", fmt.Errorf("failed to encode QR: %w", err)
	}

	dimension := code.Size
	// Add 2 modules margin on each side
	margin := 2
	totalModules := dimension + (margin * 2)
	moduleSize := float64(sizePx) / float64(totalModules)

	var sb strings.Builder
	sb.WriteString(fmt.Sprintf(`<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="%d" height="%d" viewBox="0 0 %d %d">`+"\n",
		sizePx, sizePx, sizePx, sizePx))
	sb.WriteString(fmt.Sprintf(`  <rect width="%d" height="%d" fill="#FFFFFF"/>`+"\n", sizePx, sizePx))
	sb.WriteString(`  <path d="`)

	for y := 0; y < dimension; y++ {
		for x := 0; x < dimension; x++ {
			if code.Black(x, y) {
				mx := float64(x+margin) * moduleSize
				my := float64(y+margin) * moduleSize
				sb.WriteString(fmt.Sprintf("M%.2f,%.2fh%.2fv%.2fh-%.2fz ", mx, my, moduleSize, moduleSize, moduleSize))
			}
		}
	}

	sb.WriteString(`" fill="#111827"/>` + "\n")
	sb.WriteString(`</svg>`)

	return sb.String(), nil
}
