<h1>AutoCustomerGroup - United Kingdom & Isle of Man Addon</h1>
<p>Magento 2 Module - Module to add United Kingdom & Isle of Man functionality to gwharton/module-autocustomergroup</p>
<h2>United Kingdom & Isle of Man VAT Scheme</h2>
<h3>Configuration Options</h3>
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
<p>No credentials are needed to run the integration tests.</p>

