.PHONY: help install test test-unit test-types test-coverage release

# Default target
help:
	@echo "Available commands:"
	@echo "  make install         - Install dependencies"
	@echo "  make test            - Run all tests"
	@echo "  make test-unit       - Run unit tests only"
	@echo "  make test-types      - Run PHPStan static analysis"
	@echo "  make test-coverage   - Run tests with coverage report"
	@echo "  make release VERSION=x.y.z - Run tests and tag release"
	@echo "  make clean           - Clean cache and temporary files"

# Install dependencies
install:
	@echo "📦 Installing dependencies..."
	composer install
	@echo "✅ Installation complete!"

# Run all tests
test:
	@echo "🧪 Running all tests..."
	composer test

# Run unit tests only
test-unit:
	@echo "🧪 Running unit tests..."
	composer test:unit

# Run PHPStan static analysis
test-types:
	@echo "🔍 Running PHPStan static analysis..."
	composer test:types

# Run type coverage check
test-type-coverage:
	@echo "📊 Running type coverage check..."
	composer test:type-coverage

# Run tests with coverage
test-coverage:
	@echo "📊 Running tests with coverage..."
	composer test:coverage

# Create a tagged release (requires clean working tree)
release:
	@if [ -z "$(VERSION)" ]; then \
		echo "Usage: make release VERSION=x.y.z"; \
		exit 1; \
	fi
	@echo "🔍 Checking working tree..."
	@git diff --quiet || (echo "Working tree is not clean" && exit 1)
	@echo "🧪 Running full test suite..."
	composer test
	@echo "🏷️ Creating tag v$(VERSION)..."
	@git tag -a v$(VERSION) -m "Release v$(VERSION)"
	@echo "🚀 Pushing tag to origin..."
	@git push origin v$(VERSION)

# Clean cache and temporary files
clean:
	@echo "🧹 Cleaning cache and temporary files..."
	rm -rf build/
	rm -rf vendor/
	rm -rf .phpunit.cache/
	@echo "✅ Clean complete!"
