package qris

// CRC16CCITT computes the CRC-16/CCITT-FALSE checksum mandated by the EMVCo QR
// Code specification (and therefore Indonesia's QRIS standard), and returns it
// as a 4-character uppercase hexadecimal string.
//
// Parameters: polynomial 0x1021, initial value 0xFFFF, no reflection, no final XOR.
func CRC16CCITT(input string) string {
	bytes := []byte(input)
	var crc uint16 = 0xffff

	for _, b := range bytes {
		crc ^= uint16(b) << 8
		for bit := 0; bit < 8; bit++ {
			if crc&0x8000 != 0 {
				crc = (crc << 1) ^ 0x1021
			} else {
				crc <<= 1
			}
			crc &= 0xffff
		}
	}

	const digits = "0123456789ABCDEF"
	return string([]byte{
		digits[(crc>>12)&0x0f],
		digits[(crc>>8)&0x0f],
		digits[(crc>>4)&0x0f],
		digits[crc&0x0f],
	})
}
