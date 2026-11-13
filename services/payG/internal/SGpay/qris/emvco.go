package qris

import (
	"fmt"
	"sort"
	"strconv"
	"strings"

	"fastcargo/payg/internal/core"
)

// EMVCo QRIS Tag constants
const (
	TagPayloadFormat     = "00"
	TagPointOfInitiation = "01"
	TagMerchantAccount26 = "26"
	TagMerchantAccount51 = "51"
	TagMerchantCategory  = "52"
	TagCurrencyCode      = "53"
	TagTransactionAmount = "54"
	TagCountryCode       = "58"
	TagMerchantName      = "59"
	TagMerchantCity      = "60"
	TagPostalCode        = "61"
	TagAdditionalData62  = "62"
	TagCRC               = "63"
)

const (
	POIStatic  = "11"
	POIDynamic = "12"
)

type EmvTlvMap map[string]string

// ParseEmv parses an EMVCo string payload into a tag -> value map
func ParseEmv(payload string) (EmvTlvMap, error) {
	bytes := []byte(payload)
	m := make(EmvTlvMap)
	cursor := 0

	for cursor < len(bytes) {
		malformed := func() error {
			return core.NewAppErrorf(core.CodeQRISParseError, "Malformed QRIS payload near byte %d", cursor)
		}

		if cursor+4 > len(bytes) {
			return nil, malformed()
		}
		tag := string(bytes[cursor : cursor+2])
		cursor += 2

		lengthText := string(bytes[cursor : cursor+2])
		cursor += 2
		if len(lengthText) != 2 || lengthText[0] < '0' || lengthText[0] > '9' || lengthText[1] < '0' || lengthText[1] > '9' {
			return nil, malformed()
		}
		length, _ := strconv.Atoi(lengthText)

		if cursor+length > len(bytes) {
			return nil, malformed()
		}

		value := string(bytes[cursor : cursor+length])
		cursor += length
		m[tag] = value
	}

	return m, nil
}

// EncodeTLV formats tag + 2-digit length + value
func EncodeTLV(tag, value string) (string, error) {
	byteLen := len([]byte(value))
	if byteLen > 99 {
		return "", core.NewAppErrorf(core.CodeQRISParseError, "QRIS tag %s exceeds 99 bytes limit: %d bytes", tag, byteLen)
	}
	return fmt.Sprintf("%s%02d%s", tag, byteLen, value), nil
}

// BuildEmv rebuilds payload with tags sorted numerically and CRC at the end
func BuildEmv(m EmvTlvMap) (string, error) {
	tags := make([]string, 0, len(m))
	for tag := range m {
		if tag != TagCRC {
			tags = append(tags, tag)
		}
	}

	sort.Slice(tags, func(i, j int) bool {
		ni, _ := strconv.Atoi(tags[i])
		nj, _ := strconv.Atoi(tags[j])
		return ni < nj
	})

	body := ""
	for _, tag := range tags {
		encoded, err := EncodeTLV(tag, m[tag])
		if err != nil {
			return "", err
		}
		body += encoded
	}

	withCrcHeader := body + TagCRC + "04"
	crc := CRC16CCITT(withCrcHeader)
	return withCrcHeader + crc, nil
}

// IsValidQRISChecksum checks if trailing 4-char CRC matches computed CRC
func IsValidQRISChecksum(payload string) bool {
	if len(payload) < 8 {
		return false
	}
	withoutCrc := payload[:len(payload)-4]
	provided := strings.ToUpper(payload[len(payload)-4:])
	return CRC16CCITT(withoutCrc) == provided
}
