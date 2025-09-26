# Installation Guide

5q12's Indexer offers multiple installation methods to suit different environments and preferences. Choose the method that best fits your setup and experience level.

## Installation Methods

### 1. Docker Installation (Recommended)
**Best for:** Most users, production deployments, quick setup, isolated environments

The easiest way to install 5q12's Indexer with complete environment isolation and automatic dependency management.

- ✅ Complete environment isolation
- ✅ Automatic dependency management
- ✅ Cross-platform compatibility
- ✅ Easy updates and rollbacks
- ✅ No host system modifications

**[→ Docker Installation Guide](installation-docker.md)**

### 2. Automated Script Installation
**Best for:** Debian/Ubuntu servers, system administrators, production servers

Automatic installation with dependency management and system integration for Debian-based systems.

- ✅ Automatic dependency installation
- ✅ Nginx configuration
- ✅ PHP extension management
- ✅ System service setup
- ⚠️ Requires Debian/Ubuntu (tested on Ubuntu Server)

**[→ Script Installation Guide](installation-script.md)**

### 3. Manual Installation
**Best for:** Advanced users, custom environments, learning the system

Step-by-step manual installation with full control over the process.

- ✅ Complete control over configuration
- ✅ Works on any Linux distribution
- ✅ Educational value
- ⚠️ Requires manual dependency management
- ⚠️ Requires manual configuration creation

**[→ Manual Installation Guide](installation-manual.md)**

## Quick Start

For most users, we recommend starting with **Docker Installation**:

```bash
# Create docker-compose.yml with the configuration
# Replace /your/host/path with actual directories
docker-compose up -d
```

Access at: `http://localhost:5012`

## System Requirements

**For Docker installation:**
- Docker and Docker Compose
- 512MB RAM minimum (1GB+ recommended)
- 100MB disk space

**For script/manual installation:**
- Linux-based operating system
- PHP 8.3 or higher
- Web server (Nginx recommended, Apache supported)
- 64MB RAM minimum (128MB+ recommended)
- 50MB disk space minimum

**For automated script installation:**
- Debian-based distribution (Ubuntu, Debian)
- sudo privileges
- Internet connection for package downloads

**Repository:**
- New repository location: https://ccls.icu/src/repositories/5q12-indexer/
- Download: https://ccls.icu/src/repositories/5q12-indexer/main/?download=archive

## Post-Installation

After installation with any method:

1. **Access the indexer** via your web browser
3. **Add content** to the files directory
4. **Review security settings** for production use

## Support

If you encounter issues with any installation method:

1. Check the method-specific installation guide
2. Review the [Troubleshooting Guide](troubleshooting.md)
3. Consult the [Configuration Guide](configuration.md) for post-installation setup
4. See the [Security Guide](security.md) for hardening recommendations

---

**Next Steps:** After installation, see the [Configuration Guide](configuration.md) to create your indexer settings.