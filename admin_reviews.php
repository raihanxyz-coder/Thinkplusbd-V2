<?php
// Note: This file is now included in admin_dashboard.php
// The session check and HTML structure are handled by the parent file.
?>
<style>
.review-cards-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.review-card {
    background-color: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: box-shadow 0.3s ease;
}

.review-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.review-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 10px;
}

.review-product {
    font-weight: 600;
    font-size: 1.1em;
    color: #333;
}

.review-category {
    background-color: #f5f5f5;
    color: #666;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    text-transform: capitalize;
}

.review-card-body .review-rating {
    color: #ffc107;
    font-size: 1.2em;
    margin-bottom: 10px;
}

.review-card-body .review-comment {
    color: #555;
    line-height: 1.6;
    margin-bottom: 15px;
}

.review-card-body .review-author {
    font-size: 0.9em;
    color: #888;
    font-style: italic;
}

.review-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
}

.review-status {
    font-weight: 500;
    text-transform: capitalize;
    padding: 5px 10px;
    border-radius: 5px;
    color: #fff;
}

.review-status[data-status="pending"] {
    background-color: #ffc107;
}

.review-status[data-status="approved"] {
    background-color: #28a745;
}

.review-actions .approve-btn,
.review-actions .delete-btn {
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.review-actions .approve-btn {
    background-color: #28a745;
    color: #fff;
    margin-right: 10px;
}

.review-actions .approve-btn:hover {
    background-color: #218838;
}

.review-actions .delete-btn {
    background-color: #dc3545;
    color: #fff;
}

.review-actions .delete-btn:hover {
    background-color: #c82333;
}
</style>
<div class="content-card">
    <h2 class="card-title">Manage Reviews</h2>
    <div class="form-group">
        <label for="category-filter">Category</label>
        <select id="category-filter" class="form-control">
            <option value="">Select Category</option>
        </select>
    </div>
    <div class="form-group">
        <label for="product-filter">Product</label>
        <select id="product-filter" class="form-control" disabled>
            <option value="">Select Product</option>
        </select>
    </div>
    <div id="reviews-container"></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const categoryFilter = document.getElementById('category-filter');
    const productFilter = document.getElementById('product-filter');
    const reviewsContainer = document.getElementById('reviews-container');

    // Fetch categories and populate the category filter
    fetch('get_categories.php')
        .then(response => response.json())
        .then(categories => {
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.name;
                option.textContent = category.name;
                categoryFilter.appendChild(option);
            });
        });

    // Event listener for category filter
    categoryFilter.addEventListener('change', function() {
        const selectedCategory = this.value;
        productFilter.innerHTML = '<option value="">Select Product</option>';
        productFilter.disabled = true;
        reviewsContainer.innerHTML = '';

        if (selectedCategory) {
            fetch(`get_products_by_category.php?category=${selectedCategory}`)
                .then(response => response.json())
                .then(products => {
                    productFilter.disabled = false;
                    products.forEach(product => {
                        const option = document.createElement('option');
                        option.value = product.id;
                        option.textContent = product.name;
                        productFilter.appendChild(option);
                    });
                });
        }
    });

    // Event listener for product filter
    productFilter.addEventListener('change', function() {
        const selectedProductId = this.value;
        reviewsContainer.innerHTML = '';

        if (selectedProductId) {
            fetch('get_reviews.php')
                .then(response => response.json())
                .then(reviews => {
                    const filteredReviews = reviews.filter(review => review.product_id == selectedProductId);
                    displayReviews(filteredReviews);
                });
        }
    });

    function displayReviews(reviews) {
        if (reviews.length === 0) {
            reviewsContainer.innerHTML = '<p>No reviews to display for this product.</p>';
            return;
        }
        let html = '<div class="review-cards-container">';
        reviews.forEach(review => {
            html += `
                <div class="review-card" id="review-${review.id}">
                    <div class="review-card-header">
                        <span class="review-product">${review.product_name}</span>
                        <span class="review-category">${review.category}</span>
                    </div>
                    <div class="review-card-body">
                        <div class="review-rating">${''.padStart(review.rating, '★')}</div>
                        <p class="review-comment">${review.comment}</p>
                        <span class="review-author">${review.name}</span>
                    </div>
                    <div class="review-card-footer">
                        <span class="review-status">${review.status}</span>
                        <div class="review-actions">
                            ${review.status !== 'approved' ? `<button class="approve-btn" onclick="approveReview('${review.id}', this)">Approve</button>` : ''}
                            <button class="delete-btn" onclick="deleteReview('${review.id}')">Delete</button>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        reviewsContainer.innerHTML = html;
    }

    // Load all reviews initially
    fetch('get_reviews.php')
        .then(response => response.json())
        .then(reviews => {
            displayReviews(reviews);
        });
});

function approveReview(reviewId, buttonElement) {
    fetch('approve_review.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: reviewId }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const reviewCard = document.getElementById(`review-${reviewId}`);
            const statusElement = reviewCard.querySelector('.review-status');
            statusElement.textContent = 'approved';
            buttonElement.style.display = 'none';
            // Optionally, show a success message
            const successMessage = document.createElement('span');
            successMessage.textContent = 'Approved!';
            successMessage.style.color = 'green';
            buttonElement.parentElement.insertBefore(successMessage, buttonElement);
            setTimeout(() => {
                successMessage.remove();
            }, 2000);
        } else {
            alert('Failed to approve review.');
        }
    });
}

function deleteReview(reviewId) {
    if (!confirm('Are you sure you want to delete this review?')) {
        return;
    }
    fetch('delete_review.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: reviewId }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const reviewCard = document.getElementById(`review-${reviewId}`);
            reviewCard.remove();
            // Optionally, show a success message
            alert('Review deleted successfully.');
        } else {
            alert('Failed to delete review.');
        }
    });
}
</script>
