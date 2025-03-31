<p align="center">
<a href ="https://www.youtube.com/watch?v=CxGxXiotv0I" target="_blank" title="Invoice Ninja Overview Video"><img src="https://raw.githubusercontent.com/hillelcoren/invoice-ninja/master/public/images/round_logo.png" alt="Sublime's custom image"/></a>
</p>

![v5-develop phpunit](https://github.com/invoiceninja/invoiceninja/workflows/phpunit/badge.svg?branch=v5-develop)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/d16c78aad8574466bf83232b513ef4fb)](https://www.codacy.com/gh/turbo124/invoiceninja/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=turbo124/invoiceninja&amp;utm_campaign=Badge_Grade)
<a href="https://cla-assistant.io/invoiceninja/invoiceninja"><img src="https://cla-assistant.io/readme/badge/invoiceninja/invoiceninja" alt="CLA assistant" /></a>

# Invoice Ninja 5

Invoice Ninja Version 5 is here! We've taken the best parts of Version 4 and added the most requested features to create an invoicing application like no other. Check out the **[Invoice Ninja YouTube Channel](https://www.youtube.com/@appinvoiceninja)** to get up to speed, or try the **[Demo](https://react.invoicing.co/demo)** now.

## **Choose Your Setup**
### **🔹 Hosted:**
Our hosted version is a **Software as a Service (SaaS)** solution. You're up and running in under **5 minutes**, with no need to worry about hosting or server infrastructure.

### **🔹 Self-Hosted:**
For those who prefer to manage their own hosting and server infrastructure, this version gives you **full control and flexibility**.

> 💡 **Note:** All **Pro and Enterprise** features from the hosted app are included in the source-available code. We offer a **$30 per year white-label license** to remove the Invoice Ninja branding from client-facing parts of the app.

---

## **📢 Get Social with Us**
- [Support Forum](https://forum.invoiceninja.com/)
- [Slack](http://slack.invoiceninja.com)
- [Discord](https://discord.gg/ZwEdtfCwXA)
- [Instagram](https://www.instagram.com/appinvoiceninja)

---

## **📖 Documentation**
- [Invoice Ninja API](https://invoiceninja.github.io/)
- [Developer Guide](https://invoiceninja.github.io/)
- [User Guide](https://invoiceninja.github.io/)
- [Self-Hosted Installation Guide](https://invoiceninja.github.io/)

---

## **🛠️ Installation Options and Clients**

### **📱 Mobile Apps:**
- [iPhone](https://apps.apple.com/app/id1503970375?platform=iphone)
- [Android](https://play.google.com/store/apps/details?id=com.invoiceninja.app)
- [F-Droid](https://f-droid.org/en/packages/com.invoiceninja.app)

### **💻 Desktop Apps:**
- [macOS](https://apps.apple.com/app/id1503970375?platform=mac)
- [Windows](https://microsoft.com/en-us/p/invoice-ninja/9n3f2bbcfdr6)
- Linux:
  - [Snap](https://snapcraft.io/invoiceninja)
  - [Flatpak](https://flathub.org/apps/com.invoiceninja.InvoiceNinja)

### **🖥️ Self-Hosted Server Installation**
> 💡 **Note:** The self-hosted version supports both the **desktop** and **mobile apps**.

#### **⚙️ Installation Methods**
- **Server or VM**
- **Docker File**
- **Cloudron**
- **Softaculous**
- **Elestio**
- **YunoHost**

### **🌐 Recommended Providers**
- **[Stripe](https://stripe.com)**
- **[Postmark](https://postmarkapp.com)**

---

## **🚀 Quick Hosting Setup**
In addition to the official **[Self-Hosted Installation Guide](https://invoiceninja.github.io/)**, here’s a quick setup guide:

```sh
# Clone the repository with the latest stable version
git clone --depth 1 -b v5.11.53 https://github.com/invoiceninja/invoiceninja.git  

# Copy the environment file
cp .env.example .env  

# Install dependencies
composer install -o --no-dev
```

**Note:** Replace `v5.11.53` with the latest tag version. Avoid using `v5-develop` unless you're contributing to the development branch, as it may contain work-in-progress code.

> ⚠️ **Important:** Your `APP_KEY` in the `.env` file is used to encrypt data. Losing this key will prevent the application from running.

### **📂 Load Sample Data**
Run the following command **(ensure `.env` is configured first):**
```bash
php artisan migrate:fresh --seed && php artisan db:seed && php artisan ninja:create-test-data  
```

### **🌍 Start the Web Server**
```bash
php artisan serve  
```

Now, navigate to the appropriate domain:

- **Admin Panel:**
  - **[http://localhost:8000/setup](http://localhost:8000/setup)** → To configure your installation.  
  - **[http://localhost:8000/](http://localhost:8000/)** → Administrator Login  
    - **User:** `small@example.com`  
    - **Password:** `password`  

- **Client Portal:**
  - **[http://localhost:8000/client/login](http://localhost:8000/client/login)**  
    - **User:** `user@example.com`  
    - **Password:** `password`  

---

## **🛠️ Developers Guide**
In addition to the official **[Developer Guide](https://invoiceninja.github.io/)**, here are some insights.

### **📌 App Design**
The API and client portal have been developed using **Laravel**. If you wish to contribute to this project, familiarity with **Laravel** is essential.

### **🔍 Understanding API Requests**
A typical API request follows this path:
1️⃣ **Middleware** processes the request by inspecting the requested domain and authenticating the user.
2️⃣ **Form Requests** provide authorization and validation.
3️⃣ **Controller Methods** process the request, passing it to repositories for handling.
4️⃣ **Service Classes** perform additional actions such as triggering events.
5️⃣ **Events** notify listeners to handle non-blocking tasks.
6️⃣ **Transformers** convert data into a readable format before returning a response.

## **📜 License**
Invoice Ninja is released under the **Elastic License**.
See **[LICENSE](https://github.com/invoiceninja/invoiceninja/blob/master/LICENSE)** for details.

---

## **🚀 Want More?**
Check out our other projects **[here](https://github.com/invoiceninja/)**!
