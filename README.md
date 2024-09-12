<h1>AutoCustomerGroup - United Kingdom & Isle of Man Addon</h1>
<p>Magento 2 Module - Module to add United Kingdom & Isle of Man functionality to gwharton/module-autocustomergroup</p>

<h2>United Kingdom & Isle of Man VAT Scheme</h2>
<p>This Scheme applies to shipments being sent from anywhere in the world to the United Kingdom (UK) and Isle of Man (IM). Note special procedures apply for shipments to Northern Ireland (NI, Part of UK).</p>
<p>As of 1st January 2021, all sellers must collect UK VAT at the point of sale and remit to the UK HMRC.</p>
<p>The module is capable of automatically assigning customers to the following categories.</p>
<ul>
    <li><b>Domestic</b> - For shipments wthin the UK/IM, normal UK VAT rules apply.</li>
    <li><b>Intra-EU B2B</b> - For shipments from the EU to NI and the buyer presents a validated UK VAT number, then the sale can be zero rated for UK VAT. Zero Rate notice to be included on Invoice.</li>
    <li><b>Intra-EU B2C</b> - For shipments from the EU to NI and the buyer does not present a validated UK VAT number UK VAT should be charged.</li>
    <li><b>Import B2B</b> - For shipments from outside of the UK/IM to the UK/IM and the buyer presents a validated UK VAT number, then VAT should not be charged. Reverse Charge notice to be included on Invoice.</li>
    <li><b>Import Taxed</b> - For shipments from outside of the UK/IM to the UK/IM and the total goods value is equal to or below 135 GBP, then VAT should be charged.</li>
    <li><b>Import Untaxed</b> - For shipments from outside of the UK/IM to the UK/IM, if the total goods value is above 135 GBP, then VAT should NOT be charged and instead will be collected at the UK/IM border along with any duties due.</li>
</ul>
<p>You need to create the appropriate tax rules and customer groups, and assign these customer groups to the above categories within the module configuration. Please ensure you fully understand the tax rules of the country you are shipping to. The above should only be taken as a guide.</p>

<h2>Government Information</h2>
<p>Scheme information can be found <a href="https://www.gov.uk/guidance/vat-and-overseas-goods-sold-directly-to-customers-in-the-uk" target="_blank">on the gov.uk website here</a>.</p>

<h2>Order Value</h2>
<p>For the UK VAT Scheme, the following applies (This can be confirmed
    <a href="https://www.gov.uk/guidance/vat-and-overseas-goods-sold-directly-to-customers-in-the-uk#goods-that-are-outside-the-uk-at-the-point-of-sal"
    target="_blank">here</a>) :</p>
<ul>
    <li>Total Goods value is the sum of the sale price of all items sold (including any discounts)</li>
    <li>When determining whether VAT should be charged (VAT Threshold) Shipping or Insurance Costs are not included in the value of the goods.</li>
    <li>When determining the amount of VAT to charge the Goods value does include Shipping and Insurance Costs.</li>
</ul>
<p>More information on the scheme can be found on the
    <a href="https://www.gov.uk/guidance/vat-and-overseas-goods-sold-directly-to-customers-in-the-uk" target="_blank">UK Government Website</a></p>

<h2>VAT Number Verification</h2>
<ul>
<li><b>Offline Validation</b> - A simple format validation is performed.</li>
<li><b>Online Validation</b> - In addition to the offline checks above, an online validation check is performed with the UK HMRC VAT Checking Service.</li>
</ul>

<h2>Pseudocode for group allocation</h2>
<p>Groups are allocated by evaluating the following rules in this order (If a rule matches, no further rules are evaluated).</p>
<ul>
<li>IF MerchantCountry IN UK/IM AND CustomerCountry IN UK/IM THEN Group IS Domestic.</li>
<li>IF MerchantCountry IN EU AND CustomerCountry IN NI AND TaxIdentifier IS VALID THEN Group IS IntraEUB2B.</li>
<li>IF MerchantCountry IN EU AND CustomerCountry IN NI AND TaxIdentifier IS NOT VALID THEN Group IS IntraEUB2C.</li>
<li>IF MerchantCountry IS NOT IN UK/IM AND CustomerCountry IN UK/IM AND TaxIdentifier IS VALID THEN Group IS ImportB2B.</li>
<li>IF MerchantCountry IS NOT IN UK/IM AND CustomerCountry IN UK/IM AND OrderValue IS LESS THAN OR EQUAL TO Threshold THEN Group IS ImportTaxed.</li>
<li>IF MerchantCountry IS NOT IN UK/IM AND CustomerCountry IN UK/IM AND OrderValue IS MORE THAN Threshold THEN Group IS ImportUntaxed.</li>
<li>ELSE NO GROUP CHANGE</li>
</ul>

<h2>Configuration Options</h2>
<ul>
<li><b>Enabled</b> - Enable/Disable this Tax Scheme.</li>
<li><b>Tax Identifier Field - Customer Prompt</b> - Displayed under the Tax Identifier field at checkout when a shipping country supported by this module is selected. Use this to include information to the user about why to include their Tax Identifier.</li>
<li><b>Validate Online</b> - Whether to validate VAT numbers with the HMRC VAT Validation Service, or just perform simple format validation.</li>
<li><b>Environment</b> - Whether to use the Sandbox or Production servers for the HMRC VAT Validation Service.</li>
<li><b>VAT Registration Number</b> - The UK VAT Registration Number for the Merchant. This will be provided to HMRC when all validation checks are made. Supplementary functions in AutoCustomerGroup may use this, for example displaying on invoices etc.</li>
<li><b>Import VAT Threshold</b> - If the order value is above the VAT Threshold, no VAT should be charged. The threshold here should be in Scheme Currency.</li>
<li><b>Use Magento Exchange Rate</b> - To convert from GBP Threshold to Store Currency Threshold, should we use the Magento Exchange Rate, or our own.</li>
<li><b>Exchange Rate</b> - The exchange rate to use to convert from GBP Threshold to Store Currency Threshold.</li>
<li><b>Customer Group - Domestic</b> - Merchant Country is within the UK/IM, Item is being shipped to the UK/IM.</li>
<li><b>Customer Group - Intra-EU B2B</b> - Merchant Country is within the EU, Item is being shipped to NI, VAT Number passed validation by module.</li>
<li><b>Customer Group - Intra-EU B2C</b> - Merchant Country is within the EU, Item is being shipped to NI.</li>
<li><b>Customer Group - Import B2B</b> - Merchant Country is not within the UK/IM, Item is being shipped to the UK/IM. VAT Number passed validation by module.</li>
<li><b>Customer Group - Import Taxed</b> - Merchant Country is not within the UK/IM, Item is being shipped to the UKIM, Order Value is below or equal to Import VAT Threshold.</li>
<li><b>Customer Group - Import Untaxed</b> - Merchant Country is not within the UK/Isle of Man, Item is being shipped to the UK/Isle of Man, Order Value is above the Import VAT Threshold.</li>
</ul>

<h2>Integration Tests</h2>
<p>No specific setup is required to run the integration tests.</p>

