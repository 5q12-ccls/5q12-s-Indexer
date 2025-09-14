# Installation Guide

5q12's Indexer offers multiple installation methods to suit different environments and preferences. Choose the method that best fits your setup and experience level.

## Installation Methods

### 1. Automated Script Installation (Recommended)
**Best for:** Most users, production deployments, quick setup

The easiest way to install 5q12's Indexer with automatic dependency management and configuration.

- ✅ Automatic dependency installation
- ✅ Nginx configuration
- ✅ PHP extension management
- ✅ System service setup
- ⚠️ Requires Debian/Ubuntu (tested on Ubuntu Server)

**[→ Script Installation Guide](installation-script.md)**

### 2. Manual Installation
**Best for:** Advanced users, custom environments, learning the system

Step-by-step manual installation with full control over the process.

- ✅ Complete control over configuration
- ✅ Works on any Linux distribution
- ✅ Educational value
- ⚠️ Requires manual dependency management

**[→ Manual Installation Guide](installation-manual.md)**

### 3. Docker Installation
**Best for:** Containerized environments, development, isolation

Run 5q12's Indexer in a containerized environment with Docker.

- 🚧 **Coming Soon / Currently Being Worked On**
- Will support Docker Compose
- Planned multi-architecture support
- Development and production variants

## Quick Start

For most users, we recommend starting with the **Automated Script Installation**:

```bash
# Download the installer
wget https://github.com/5q12-ccls/5q12-s-Indexer/raw/main/install.sh

# Make it executable
chmod +x install.sh

# Run installation (requires sudo)
sudo ./install.sh install /var/www/html/files
```

## System Requirements

**Minimum requirements for all installation methods:**
- Linux-based operating system
- PHP 8.3 or higher
- Web server (Nginx recommended, Apache supported)
- 64MB RAM minimum (128MB+ recommended)
- 50MB disk space minimum

**For automated script installation:**
- Debian-based distribution (Ubuntu, Debian)
- sudo privileges
- Internet connection for package downloads

## Support

If you encounter issues with any installation method:

1. Check the [Troubleshooting Guide](troubleshooting.md)
2. Review the [Configuration Guide](configuration.md) for post-installation setup
3. Consult the [Security Guide](security.md) for hardening recommendations

---

**Next Steps:** After installation, see the [Configuration Guide](configuration.md) to customize your indexer settings.