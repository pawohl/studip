// Topics
export const getTopicURL = id => STUDIP.URLHelper.getURL(`dispatch.php/course/forum/topics/show/${id}`);
export const getTopicEditURL = id => STUDIP.URLHelper.getURL(`dispatch.php/course/forum/topics/edit/${id}`);
export const getTopicDeleteURL = id => STUDIP.URLHelper.getURL(`dispatch.php/course/forum/topics/delete/${id}`);

// Discussions
export const getDiscussionIndexURL = () => STUDIP.URLHelper.getURL('dispatch.php/course/forum/discussions/index');
export const getDiscussionCreateURL = () => STUDIP.URLHelper.getURL('dispatch.php/course/forum/discussions/edit');
export const getDiscussionURL = (discussion_id, params = {}) => STUDIP.URLHelper.getURL(`dispatch.php/course/forum/discussions/show/${discussion_id}`, params);

// Posts
export const getPostURL = (discussionId, postId) => STUDIP.URLHelper.getURL(`dispatch.php/course/forum/discussions/show/${discussionId}#post_${postId}`)

// Categories
export const getCategoryURL = id => STUDIP.URLHelper.getURL(`dispatch.php/course/forum/categories/show/${id}`);
export const getCategoryCreateURL = () => STUDIP.URLHelper.getURL('dispatch.php/course/forum/categories/edit');
export const getCategoryEditURL = id => STUDIP.URLHelper.getURL(`dispatch.php/course/forum/categories/edit/${id}`);
export const getCategoryDeleteURL = id => STUDIP.URLHelper.getURL(`dispatch.php/course/forum/categories/delete/${id}`);

export const getSearchURL = (hashtags='') => STUDIP.URLHelper.getURL(`dispatch.php/course/forum/search?${hashtags}`);

export const userProfileURL = username => STUDIP.URLHelper.getURL('dispatch.php/profile', {username});

// Discussion Types:
export const getDiscussionTypeEditURL = (id = null) => STUDIP.URLHelper.getURL(`dispatch.php/course/forum/discussion_types/edit/${id}`);
export const getDiscussionTypeStoreURL = (id = null) => STUDIP.URLHelper.getURL(`dispatch.php/course/forum/discussion_types/save/${id}`);
