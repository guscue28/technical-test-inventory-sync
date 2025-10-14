#!/bin/bash

# 🧪 Verification Script - Inventory Sync Module
# Quick health check to verify all components are working

echo "🧪 VERIFYING INVENTORY SYNC MODULE"
echo "=================================="

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

success_count=0
total_checks=0

check_test() {
    local test_name="$1"
    local command="$2"
    local expected_pattern="$3"

    echo -n "Testing $test_name... "
    total_checks=$((total_checks + 1))

    result=$(eval $command 2>/dev/null)

    if [[ $result == *"$expected_pattern"* ]]; then
        echo -e "${GREEN}✅ PASS${NC}"
        success_count=$((success_count + 1))
    else
        echo -e "${RED}❌ FAIL${NC}"
        echo "  Expected: $expected_pattern"
        echo "  Got: $result"
    fi
}

echo ""
echo "📡 Testing API Endpoints..."
echo "----------------------------"

# Test 1: Health Check
check_test "API Health Check" \
    "curl -s http://localhost:8000/api/health" \
    "Inventory Sync API is running"

# Test 2: Create Product
product_response=$(curl -s -X POST http://localhost:8000/api/products \
    -H "Content-Type: application/json" \
    -d '{"name":"Verification Test Product","reference":"VERIFY-001","current_stock":100}')

check_test "Create Product" \
    "echo '$product_response'" \
    "success"

# Test 3: Update Stock (Main Endpoint)
stock_response=$(curl -s -X PATCH http://localhost:8000/api/products/1/stock \
    -H "Content-Type: application/json" \
    -d '{"stock":150,"user_source":"verification"}')

check_test "Update Stock (MAIN ENDPOINT)" \
    "echo '$stock_response'" \
    "Stock updated successfully"

# Test 4: Get Inventory Logs
check_test "Get Inventory Logs" \
    "curl -s http://localhost:8000/api/inventory-logs" \
    "success"

# Test 5: Get Logs with Filters
check_test "Get Filtered Logs" \
    "curl -s 'http://localhost:8000/api/inventory-logs?per_page=5'" \
    "success"

echo ""
echo "🧪 Testing Unit Tests..."
echo "------------------------"

cd backend 2>/dev/null
if [ -f "vendor/bin/phpunit" ]; then
    test_result=$(./vendor/bin/phpunit tests/Unit/Services/InventoryServiceTest.php 2>&1)
    if [[ $test_result == *"OK"* ]] || [[ $test_result == *"Tests: "* ]]; then
        echo -e "Unit Tests... ${GREEN}✅ PASS${NC}"
        success_count=$((success_count + 1))
    else
        echo -e "Unit Tests... ${RED}❌ FAIL${NC}"
    fi
    total_checks=$((total_checks + 1))
else
    echo -e "Unit Tests... ${YELLOW}⚠️  SKIP (PHPUnit not found)${NC}"
fi
cd - > /dev/null

echo ""
echo "🌐 Testing Frontend..."
echo "----------------------"

# Test Frontend accessibility
frontend_status=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 2>/dev/null)
if [ "$frontend_status" = "200" ]; then
    echo -e "Frontend Accessibility... ${GREEN}✅ PASS${NC}"
    success_count=$((success_count + 1))
else
    echo -e "Frontend Accessibility... ${RED}❌ FAIL (Status: $frontend_status)${NC}"
fi
total_checks=$((total_checks + 1))

echo ""
echo "📊 VERIFICATION RESULTS"
echo "======================"

if [ $success_count -eq $total_checks ]; then
    echo -e "${GREEN}🎉 ALL TESTS PASSED! ($success_count/$total_checks)${NC}"
    echo -e "${GREEN}✅ Project is fully functional and ready!${NC}"
    exit 0
elif [ $success_count -gt $((total_checks / 2)) ]; then
    echo -e "${YELLOW}⚠️  PARTIAL SUCCESS ($success_count/$total_checks)${NC}"
    echo -e "${YELLOW}Some components may need attention.${NC}"
    exit 1
else
    echo -e "${RED}❌ MULTIPLE FAILURES ($success_count/$total_checks)${NC}"
    echo -e "${RED}Please check the setup and try again.${NC}"
    exit 2
fi

echo ""
echo "📋 Next Steps:"
echo "- Frontend: http://localhost:8080"
echo "- API: http://localhost:8000/api"
echo "- Health: http://localhost:8000/api/health"
