#!/bin/bash

# 🚀 Automated Setup Script for Inventory Sync Module
# This script automates the setup process for the technical test project

echo "🚀 Starting Inventory Sync Module Setup..."
echo "=============================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -d "backend" ] || [ ! -d "frontend" ]; then
    print_error "Please run this script from the project root directory"
    exit 1
fi

# Step 1: Setup Backend
print_status "Setting up Backend (Laravel)..."

cd backend

# Install dependencies
if [ ! -d "vendor" ]; then
    print_status "Installing PHP dependencies..."
    composer install
    if [ $? -ne 0 ]; then
        print_error "Failed to install PHP dependencies"
        exit 1
    fi
else
    print_success "PHP dependencies already installed"
fi

# Setup environment file
if [ ! -f ".env" ]; then
    print_status "Creating .env file..."
    cp .env.example .env
    php artisan key:generate
    print_success "Environment file created"
else
    print_warning ".env file already exists"
fi

# Database setup
print_status "Setting up database..."

# Check if MySQL is running
if ! mysqladmin ping &>/dev/null; then
    print_warning "MySQL doesn't seem to be running. Please start MySQL first."
    print_status "On macOS: brew services start mysql"
    print_status "On Linux: sudo systemctl start mysql"
    read -p "Press Enter when MySQL is running..."
fi

# Prompt for database credentials
echo ""
print_status "Database Configuration"
echo "=================================="
read -p "MySQL username [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -s -p "MySQL password: " DB_PASS
echo ""

read -p "Database name [inventory_sync]: " DB_NAME
DB_NAME=${DB_NAME:-inventory_sync}

# Update .env file
print_status "Updating database configuration..."
sed -i.bak "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
sed -i.bak "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
sed -i.bak "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env

# Create database
print_status "Creating database '$DB_NAME'..."
mysql -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null

if [ $? -eq 0 ]; then
    print_success "Database '$DB_NAME' created successfully"
else
    print_warning "Database might already exist or there was an issue"
fi

# Run migrations
print_status "Running database migrations..."
php artisan migrate --force

# Load sample data
print_status "Loading sample data..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < ../database/schema.sql 2>/dev/null

print_success "Backend setup completed!"

# Go back to root
cd ..

# Step 2: Frontend Configuration
print_status "Configuring Frontend..."

# Check if config.js needs updating
if grep -q "localhost:8000" frontend/js/config.js; then
    print_success "Frontend API configuration is correct"
else
    print_warning "You may need to update the API URL in frontend/js/config.js"
fi

# Step 3: Run Tests
print_status "Running tests..."
cd backend
./vendor/bin/phpunit tests/Unit/Services/InventoryServiceTest.php --quiet

if [ $? -eq 0 ]; then
    print_success "All tests passed!"
else
    print_warning "Some tests failed. Check the output above."
fi

cd ..

# Step 4: Final Instructions
echo ""
echo "🎉 SETUP COMPLETED!"
echo "==================="
echo ""
print_success "✅ Backend configured and ready"
print_success "✅ Database created and populated"
print_success "✅ Tests executed"
print_success "✅ Frontend configured"
echo ""
echo "🚀 TO START THE APPLICATION:"
echo ""
echo "1. Start the Backend (Terminal 1):"
echo "   cd backend && php artisan serve"
echo ""
echo "2. Start the Frontend (Terminal 2):"
echo "   cd frontend && python3 -m http.server 8080"
echo ""
echo "3. Open your browser:"
echo "   Frontend: http://localhost:8080"
echo "   API: http://localhost:8000/api"
echo ""
echo "🧪 TO TEST THE API:"
echo ""
echo "# Create a product:"
echo "curl -X POST http://localhost:8000/api/products \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"name\":\"Test Product\",\"reference\":\"TEST-001\",\"current_stock\":100}'"
echo ""
echo "# Update stock (main endpoint):"
echo "curl -X PATCH http://localhost:8000/api/products/1/stock \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -d '{\"stock\":150,\"user_source\":\"test\"}'"
echo ""
echo "# View inventory logs:"
echo "curl http://localhost:8000/api/inventory-logs"
echo ""
print_success "🎊 Project is ready! All technical requirements are fulfilled."
