const defaultPosts = [
  {
    id: 'portfolio',
    title: 'Launch a one-page portfolio in 24 hours',
    category: 'portfolio',
    categoryLabel: 'Portfolio',
    date: 'May 30, 2026',
    readTime: '7 min read',
    excerpt: 'See how a simple portfolio can show your best work and help people understand what you do.',
    image: 'blog port.jpg',
    imageAlt: 'Illustration for portfolio launch',
    content: [
      {
        heading: 'Step 1: Pick your focus',
        body: 'Focus on two or three topics you enjoy. If you are building a personal site, this could include portfolio case studies, project notes, tutorials, and tools you use every day.'
      },
      {
        heading: 'Step 2: Use a repeatable post structure',
        body: 'Each article should be easy to write and easy to read. Start with an introduction, explain one clear lesson, add examples, and end with a next action for the reader.'
      },
      {
        heading: 'Step 3: Keep your site simple',
        body: 'A homepage, blog page, about page, and contact page are enough. Focus on readable text, a strong headline, and clear navigation.'
      }
    ]
  },
  {
    id: 'writing',
    title: 'Write blog posts that feel easy',
    category: 'writing',
    categoryLabel: 'Writing',
    date: 'May 24, 2026',
    readTime: '5 min read',
    excerpt: 'Use a repeatable structure so writing stops feeling like a huge task and starts feeling like a process.',
    image: 'post2.jpg',
    imageAlt: 'Illustration for blog writing',
    content: [
      {
        heading: 'Start with one useful idea',
        body: 'Choose one lesson, problem, or experience. A focused article is easier to write and easier for readers to remember.'
      },
      {
        heading: 'Use a simple outline',
        body: 'Introduce the topic, explain the main point, add examples or personal notes, and end with a helpful next action.'
      },
      {
        heading: 'Keep publishing realistic',
        body: 'A good blog grows through consistency. Start with short posts, improve them later, and keep your writing schedule possible.'
      }
    ]
  },
  {
    id: 'contact-page',
    title: 'Design a contact page people actually use',
    category: 'ux',
    categoryLabel: 'UX',
    date: 'May 18, 2026',
    readTime: '4 min read',
    excerpt: 'Clear labels, a friendly message, and fewer fields make it much easier for visitors to reach out.',
    image: 'post3.png',
    imageAlt: 'Illustration for contact page design',
    content: [
      {
        heading: 'Make the form short',
        body: 'Ask only for the details you need: name, email, and message. Fewer fields make people more likely to finish.'
      },
      {
        heading: 'Add helpful details',
        body: 'Show an email address, add a response time, and use clear placeholder text to guide the message.'
      },
      {
        heading: 'Keep the page friendly',
        body: 'Simple copy and a clean layout can make a contact page feel more welcoming and professional.'
      }
    ]
  }
];

const BlogStore = {
  key: 'brightpath-posts',

  async getPosts() {
    const serverPosts = await this.fetchServerPosts();
    const localPosts = this.getLocalPosts();

    if (localPosts.length) {
      this.syncLocalPosts(localPosts, serverPosts);
    }

    return this.dedupePosts([...serverPosts, ...localPosts, ...defaultPosts]);
  },

  async syncLocalPosts(localPosts, serverPosts) {
    const serverIds = new Set(serverPosts.map((post) => post.id));
    const unsyncedPosts = localPosts.filter((post) => post && post.id && !serverIds.has(post.id));
    if (!unsyncedPosts.length) {
      localStorage.removeItem(this.key);
      return;
    }

    try {
      await Promise.all(unsyncedPosts.map((post) => this.savePostToServer(post)));
      localStorage.removeItem(this.key);
    } catch (error) {
      // Keep local posts if the server is unavailable, so they can sync later.
    }
  },

  async fetchServerPosts() {
    try {
      const response = await fetch('api/posts.php', { cache: 'no-store' });
      if (!response.ok) throw new Error('Could not load server posts');
      return await response.json();
    } catch (error) {
      return [];
    }
  },

  getLocalPosts() {
    return JSON.parse(localStorage.getItem(this.key) || '[]');
  },

  async savePostToServer(post) {
    const response = await fetch('api/posts.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(post)
    });
    if (!response.ok) throw new Error('Could not save to server');
    return await response.json();
  },

  async savePost(post) {
    try {
      const result = await this.savePostToServer(post);
      localStorage.removeItem(this.key);
      return result;
    } catch (error) {
      const saved = this.getLocalPosts().filter((item) => item.id !== post.id);
      saved.unshift(post);
      localStorage.setItem(this.key, JSON.stringify(saved));
      return { ok: true, fallback: true };
    }
  },

  async getSavedPosts() {
    return await this.fetchServerPosts();
  },

  async deletePost(id) {
    try {
      await fetch(`api/posts.php?id=${encodeURIComponent(id)}`, {
        method: 'DELETE'
      });
    } catch (error) {
      const saved = this.getLocalPosts().filter((post) => post.id !== id);
      localStorage.setItem(this.key, JSON.stringify(saved));
    }
  },

  async getPost(id) {
    const posts = await this.getPosts();
    return posts.find((post) => post.id === id) || posts[0];
  },

  dedupePosts(posts) {
    const seen = new Set();
    return posts.filter((post) => {
      if (!post || !post.id || seen.has(post.id)) return false;
      seen.add(post.id);
      return true;
    });
  },

  slugify(value) {
    return value
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  },

  escape(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }
};
