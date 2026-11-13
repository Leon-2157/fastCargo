package allocator

import (
	"sync"
	"testing"
)

func TestAllocatorSequencing(t *testing.T) {
	alloc := NewAllocator(10)
	taken := make(map[int64]bool)

	// Base amount 10000
	off1, err := alloc.Allocate(10000, taken)
	if err != nil || off1 != 1 {
		t.Fatalf("expected offset 1, got %d, err: %v", off1, err)
	}
	taken[10000+off1] = true

	off2, err := alloc.Allocate(10000, taken)
	if err != nil || off2 != 2 {
		t.Fatalf("expected offset 2, got %d, err: %v", off2, err)
	}
	taken[10000+off2] = true

	// Simulate freeing offset 1
	delete(taken, 10001)

	off3, err := alloc.Allocate(10000, taken)
	if err != nil || off3 != 1 {
		t.Fatalf("expected reused offset 1, got %d, err: %v", off3, err)
	}
}

func TestAllocatorExhaustion(t *testing.T) {
	alloc := NewAllocator(3)
	taken := map[int64]bool{
		5001: true,
		5002: true,
		5003: true,
	}

	_, err := alloc.Allocate(5000, taken)
	if err == nil {
		t.Fatalf("expected exhaustion error when slots 1..3 are taken")
	}
}

func TestConcurrentAllocation(t *testing.T) {
	const goroutines = 999
	alloc := NewAllocator(goroutines)

	var mu sync.Mutex
	taken := make(map[int64]bool)

	var wg sync.WaitGroup
	errs := make(chan error, goroutines)
	allocated := make(map[int64]bool)

	for i := 0; i < goroutines; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()

			mu.Lock()
			offset, err := alloc.Allocate(10000, taken)
			if err != nil {
				mu.Unlock()
				errs <- err
				return
			}
			taken[10000+offset] = true
			allocated[offset] = true
			mu.Unlock()
		}()
	}

	wg.Wait()
	close(errs)

	for err := range errs {
		t.Fatalf("unexpected allocation error in concurrent execution: %v", err)
	}

	if len(allocated) != goroutines {
		t.Fatalf("expected %d unique offsets allocated, got %d", goroutines, len(allocated))
	}
}
