# Atropos Instrumentation

This tool will automatically scan through a WordPress instance to instrument so-called "critical sinks" with crash-reporting code. This instrumentor is meant to be used along with the "Atropos" fuzzer.

## Installation
```bash
git clone https://github.com/david-prv/atropos-instrumentation.git
cd atropos-instrumentation
composer install
```

## Usage (WordPress)
```bash
# Install WordPress CLI tool
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# Download test instance
wp core download --path=./target

# Run instrumentation
php ./src/instrumentor.php ../target
```