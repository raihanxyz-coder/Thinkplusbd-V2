<?php
// Note: This file is now included in admin_dashboard.php
// The session check and HTML structure are handled by the parent file.
?>
<div class="content-card">
    <h2 class="card-title">All Coupons</h2>
    <div id="coupons-container"></div>
</div>
<style>
#create-coupon-form .form-group {
    margin-bottom: 1.5rem;
}

#create-coupon-form label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

#create-coupon-form input[type="text"],
#create-coupon-form input[type="number"],
#create-coupon-form select {
    width: 100%;
    padding: 0.75rem;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    box-sizing: border-box;
}

#create-coupon-form .coupon-code-container {
    display: flex;
}

#create-coupon-form #coupon-code {
    flex-grow: 1;
}

#create-coupon-form #generate-random-code {
    width: 150px;
    margin-left: 10px;
    padding: 0.75rem;
    border: none;
    background-color: var(--primary-color);
    color: white;
    border-radius: var(--border-radius);
    cursor: pointer;
}

#create-coupon-form button[type="submit"] {
    width: 100%;
    padding: 0.75rem;
    border: none;
    background-color: var(--primary-color);
    color: white;
    border-radius: var(--border-radius);
    cursor: pointer;
    font-size: 1rem;
    font-weight: 500;
}
</style>
<div class="content-card">
    <h2 class="card-title">Create Coupon</h2>
    <form id="create-coupon-form">
        <div class="form-group">
            <label for="coupon-code">Coupon Code</label>
            <div class="coupon-code-container">
                <input type="text" id="coupon-code">
                <button type="button" id="generate-random-code">Generate Random</button>
            </div>
        </div>
        <div class="form-group">
            <label for="category-select">Category</label>
            <select id="category-select" required>
                <option value="">-- Select a category --</option>
            </select>
        </div>
        <div class="form-group">
            <label for="product-select">Products</label>
            <select id="product-select" multiple required></select>
        </div>
        <div class="form-group">
            <label for="discount-type">Discount Type</label>
            <select id="discount-type">
                <option value="percentage">Percentage</option>
                <option value="fixed">Fixed Amount</option>
            </select>
        </div>
        <div class="form-group">
            <label for="discount-value">Discount Value</label>
            <input type="number" id="discount-value" required>
        </div>
        <button type="submit">Create New Coupon</button>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fetch and display existing coupons
    fetch('get_coupons.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('coupons-container');
            if (data.error) {
                container.innerHTML = `<p>${data.error}</p>`;
                return;
            }
            if (data.length === 0) {
                container.innerHTML = '<p>No coupons to display.</p>';
                return;
            }
            let html = '<table>';
            html += '<tr><th>Code</th><th>Discount</th><th>Product IDs</th><th>Category</th><th>Action</th></tr>';
            data.forEach(coupon => {
                html += `
                    <tr>
                        <td>${coupon.code}</td>
                        <td>${coupon.discount_value}${coupon.discount_type === 'percentage' ? '%' : ' Taka'}</td>
                        <td>${coupon.product_ids ? coupon.product_ids.join(', ') : 'All'}</td>
                        <td>${coupon.category || 'All'}</td>
                        <td>
                            <button onclick="deleteCoupon('${coupon.code}')">Delete</button>
                        </td>
                    </tr>
                `;
            });
            html += '</table>';
            container.innerHTML = html;
        });

    // Populate category dropdown
    const categorySelect = document.getElementById('category-select');
    const productSelect = document.getElementById('product-select');
    let allProducts = [];

    fetch('get_categories.php')
        .then(response => response.json())
        .then(categories => {
            categories.forEach(category => {
                const option = new Option(category.name, category.name);
                categorySelect.add(option);
            });
        });

    fetch('get_products.php')
        .then(response => response.json())
        .then(products => {
            allProducts = products;
        });

    categorySelect.addEventListener('change', function() {
        const selectedCategory = this.value;
        productSelect.innerHTML = '';
        if (selectedCategory) {
            const filteredProducts = allProducts.filter(product => product.category === selectedCategory);
            filteredProducts.forEach(product => {
                const option = new Option(product.name, product.id);
                productSelect.add(option);
            });
        }
    });

    // Random code generation
    document.getElementById('generate-random-code').addEventListener('click', function() {
        const randomCode = Math.random().toString(36).substring(2, 10).toUpperCase();
        document.getElementById('coupon-code').value = randomCode;
    });

    const createCouponForm = document.getElementById('create-coupon-form');

    createCouponForm.addEventListener('submit', function(event) {
        event.preventDefault();

        const code = document.getElementById('coupon-code').value;
        const discount_type = document.getElementById('discount-type').value;
        const discount_value = document.getElementById('discount-value').value;
        const category = categorySelect.value;
        const product_ids = Array.from(productSelect.selectedOptions).map(option => option.value);

        if (!category) {
            alert('Please select a category.');
            return;
        }

        if (product_ids.length === 0) {
            alert('Please select at least one product.');
            return;
        }

        fetch('create_coupon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                code,
                discount_type,
                discount_value,
                category,
                product_ids
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to create coupon: ' + (data.message || ''));
            }
        });
    });
});

function deleteCoupon(couponCode) {
    if (!confirm('Are you sure you want to delete this coupon?')) {
        return;
    }
    fetch('delete_coupon.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ code: couponCode }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to delete coupon.');
        }
    });
}
</script>
