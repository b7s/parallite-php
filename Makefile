.PHONY: help install test test-unit test-types test-coverage test-plugin release

RELEASE_VERSION := $(if $(VERSION),$(VERSION),$(version))
RELEASE_MESSAGE := $(if $(MESSAGE),$(MESSAGE),$(message))

# Default target
help:
	@echo "Available commands:"
	@echo "  make install         - Install dependencies"
	@echo "  make test            - Run all tests"
	@echo "  make test-unit       - Run unit tests only"
	@echo "  make test-types      - Run PHPStan static analysis"
	@echo "  make test-coverage   - Run tests with coverage report"
	@echo "  make test-plugin     - Test Composer plugin in fresh project"
	@echo "  make release version=x.y.z message='msg' - Run tests and tag release"
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

# Test Composer plugin in fresh project
test-plugin:
	@echo "🔌 Testing Composer plugin..."
	./test-plugin.sh

# Create a tagged release (requires clean working tree)
release:
	@CURRENT_VERSION=$$(php -r 'echo json_decode(file_get_contents("composer.json"), true)["version"] ?? "0.0.0";'); \
	echo "Current version: $$CURRENT_VERSION"; \
	VERSION_INPUT="$(RELEASE_VERSION)"; \
	if [ -z "$$VERSION_INPUT" ]; then \
		read -p "Enter release version (format 0.0.0): " VERSION_INPUT; \
	fi; \
	if ! echo "$$VERSION_INPUT" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$$'; then \
		echo "Invalid version format. Expected 0.0.0"; exit 1; \
	fi; \
	echo "New version: $$VERSION_INPUT"; \
	MESSAGE_INPUT="$(RELEASE_MESSAGE)"; \
	if [ -z "$$MESSAGE_INPUT" ]; then \
		echo "Enter commit message (press Enter for default, Ctrl+D when done for multi-line):"; \
		MESSAGE_INPUT=$$(cat); \
		if [ -z "$$MESSAGE_INPUT" ]; then \
			MESSAGE_INPUT="chore: release v$$VERSION_INPUT"; \
		fi; \
	fi; \
	echo "🔍 Checking working tree..."; \
	git diff --quiet || (echo "Working tree is not clean" && exit 1); \
	echo "🧪 Running full test suite..."; \
	composer test; \
	echo "📝 Updating composer.json version..."; \
	composer config version "$$VERSION_INPUT"; \
	echo "✅ Staging release files..."; \
	git add -A; \
	echo "📝 Creating release commit..."; \
	git commit -m "$$MESSAGE_INPUT"; \
	echo "🏷️ Creating tag v$$VERSION_INPUT..."; \
	git tag -a v$$VERSION_INPUT -m "Release v$$VERSION_INPUT"; \
	echo "🚀 Pushing commit..."; \
	git push origin HEAD; \
	echo "🚀 Pushing tag..."; \
	git push origin v$$VERSION_INPUT

# Clean cache and temporary files
clean:
	@echo "🧹 Cleaning cache and temporary files..."
	rm -rf build/
	rm -rf vendor/
	rm -rf .phpunit.cache/
	@echo "✅ Clean complete!"
