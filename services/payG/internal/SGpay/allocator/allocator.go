package allocator

import (
	"sync"

	"fastcargo/payg/internal/core"
)

type Allocator struct {
	mu        sync.Mutex
	maxOffset int64
}

func NewAllocator(maxOffset int64) *Allocator {
	if maxOffset <= 0 {
		maxOffset = 999
	}
	return &Allocator{maxOffset: maxOffset}
}

// Allocate returns the smallest offset in [1, maxOffset] such that baseAmount + offset is not in takenAmounts
func (a *Allocator) Allocate(baseAmount int64, takenAmounts map[int64]bool) (int64, error) {
	a.mu.Lock()
	defer a.mu.Unlock()

	for offset := int64(1); offset <= a.maxOffset; offset++ {
		candidate := baseAmount + offset
		if !takenAmounts[candidate] {
			return offset, nil
		}
	}

	return 0, core.NewAppErrorf(core.CodeAmountPoolExhausted,
		"All unique amount slots [1..%d] are currently in use for base amount %d", a.maxOffset, baseAmount)
}

func (a *Allocator) Max() int64 {
	return a.maxOffset
}
