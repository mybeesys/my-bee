<div>
    {{-- The whole world belongs to you. --}}
    <div class="card current-plan">
        <div class="card-header">
            <h2>Current Plan: Pro</h2>
            <p>Your subscription details</p>
        </div>
        <div class="card-content">
            <div class="plan-details">
                <div class="detail">
                    <i data-lucide="calendar"></i>
                    <span>Start date: 01/01/2023</span>
                </div>
                <div class="detail">
                    <i data-lucide="calendar"></i>
                    <span>Next billing date: 07/01/2023</span>
                </div>
                <div class="price">
                    $30<span>/month</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card change-plan">
        <div class="card-header">
            <h2>Change Your Plan</h2>
            <p>Choose the plan that works best for you</p>
        </div>
        <div class="card-content">
            <div class="billing-toggle">
                <label for="billing-toggle">Annual billing</label>
                <input type="checkbox" id="billing-toggle" class="toggle">
            </div>
            <div class="plans">
                <label class="plan">
                    <input type="radio" name="plan" value="starter">
                    <div class="plan-content">
                        <div class="plan-header">
                            <h3>Starter</h3>
                            <div class="plan-price">$15<span>/month</span></div>
                        </div>
                        <ul class="features">
                            <li><i data-lucide="check"></i>Up to 5 projects</li>
                            <li><i data-lucide="check"></i>5GB storage</li>
                            <li><i data-lucide="check"></i>Basic support</li>
                        </ul>
                    </div>
                </label>
                <label class="plan">
                    <input type="radio" name="plan" value="pro" checked>
                    <div class="plan-content">
                        <div class="plan-header">
                            <h3>Pro</h3>
                            <div class="plan-price">$30<span>/month</span></div>
                        </div>
                        <ul class="features">
                            <li><i data-lucide="check"></i>Up to 15 projects</li>
                            <li><i data-lucide="check"></i>15GB storage</li>
                            <li><i data-lucide="check"></i>Priority support</li>
                            <li><i data-lucide="check"></i>Advanced analytics</li>
                        </ul>
                    </div>
                </label>
                <label class="plan">
                    <input type="radio" name="plan" value="enterprise">
                    <div class="plan-content">
                        <div class="plan-header">
                            <h3>Enterprise</h3>
                            <div class="plan-price">$60<span>/month</span></div>
                        </div>
                        <ul class="features">
                            <li><i data-lucide="check"></i>Unlimited projects</li>
                            <li><i data-lucide="check"></i>Unlimited storage</li>
                            <li><i data-lucide="check"></i>24/7 dedicated support</li>
                            <li><i data-lucide="check"></i>Custom integrations</li>
                        </ul>
                    </div>
                </label>
            </div>
        </div>
        <div class="card-footer">
            <button class="button">Update Subscription</button>
        </div>
    </div>

    <style>
        :root {
            --primary: #3b82f6;
            --primary-foreground: #ffffff;
            --muted: #e2e8f0;
            --muted-foreground: #64748b;
            --accent: #f1f5f9;
            --accent-foreground: #0f172a;
            --background: #ffffff;
            --card: #ffffff;
            --card-foreground: #0f172a;
            --border: #e2e8f0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.5;
            color: var(--card-foreground);
            background-color: var(--background);
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.25rem;
        }

        .card-header p {
            margin: 0.5rem 0 0;
            color: var(--muted-foreground);
            font-size: 0.875rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        .card-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .plan-details {
            display: grid;
            gap: 1rem;
        }

        .detail {
            display: flex;
            align-items: center;
            color: var(--muted-foreground);
            font-size: 0.875rem;
        }

        .detail i {
            margin-right: 0.5rem;
        }

        .price {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .price span {
            font-size: 0.875rem;
            font-weight: normal;
            color: var(--muted-foreground);
        }

        .billing-toggle {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 1rem;
        }

        .toggle {
            margin-left: 0.5rem;
        }

        .plans {
            display: grid;
            gap: 1.5rem;
        }

        .plan {
            cursor: pointer;
        }

        .plan input {
            display: none;
        }

        .plan-content {
            border: 1px solid var(--border);
            border-radius: 0.25rem;
            padding: 1rem;
            transition: all 0.2s ease;
        }

        .plan input:checked + .plan-content {
            border-color: var(--primary);
            background-color: var(--accent);
        }

        .plan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .plan-header h3 {
            margin: 0;
            font-size: 1rem;
        }

        .plan-price {
            font-weight: bold;
        }

        .plan-price span {
            font-size: 0.75rem;
            font-weight: normal;
            color: var(--muted-foreground);
        }

        .features {
            list-style-type: none;
            padding: 0;
            margin: 0;
            font-size: 0.875rem;
        }

        .features li {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .features i {
            color: var(--primary);
            margin-right: 0.5rem;
        }

        .button {
            background-color: var(--primary);
            color: var(--primary-foreground);
            border: none;
            border-radius: 0.25rem;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.2s ease;
        }

        .button:hover {
            background-color: #2563eb;
        }

        @media (min-width: 640px) {
            .plans {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</div>
