<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by WeCenter Software
|   © 2011 - 2013 WeCenter. All Rights Reserved
|   http://www.wecenter.com
|   ========================================
|   Support: WeCenter@qq.com
|   
+---------------------------------------------------------------------------
*/


if (!defined('IN_ANWSION'))
{
	die;
}

class main extends AWS_CONTROLLER
{
	public function get_access_rule()
	{
		$rule_action['rule_type'] = 'white';
		
		if ($this->user_info['permission']['visit_question'] AND $this->user_info['permission']['visit_site'])
		{
			$rule_action['actions'] = array(
				'index'
			);
		}
		
		return $rule_action;
	}

	public function index_action()
	{
		if (! isset($_GET['id']))
		{
			HTTP::redirect('/home/explore/');
		}
		
		if ($_GET['notification_id'])
		{
			$this->model('notify')->read_notification($_GET['notification_id'], $this->user_id);
		}
		
		if (is_mobile() AND HTTP::get_cookie('_ignore_ua_check') != 'TRUE')
		{
			HTTP::redirect('/m/question/' . $_GET['id']);
		}
		
		if ($_GET['column'] == 'log' AND !$this->user_id)
		{
			HTTP::redirect('/question/' . $_GET['id']);
		}
		
		if (! $question_info = $this->model('question')->get_question_info_by_id($_GET['id']))
		{
			H::redirect_msg(AWS_APP::lang()->_t('问题不存在或已被删除'), '/home/explore/');
		}
		
		if (! $_GET['sort'] or $_GET['sort'] != 'ASC')
		{
			$_GET['sort'] = 'DESC';
		}
		else
		{
			$_GET['sort'] = 'ASC';
		}
		
		if (get_setting('unfold_question_comments') == 'Y')
		{
			$_GET['comment_unfold'] = 'all';
		}
		
		/*if (!$question_info['question_content_fulltext'])
		{
			$this->model('search_fulltext')->push_index('question', $question_info['question_content'], $question_info['question_id']);
		}*/
		
		$question_info['redirect'] = $this->model('question')->get_redirect($question_info['question_id']);
		
		if ($question_info['redirect']['target_id'])
		{
			$target_question = $this->model('question')->get_question_info_by_id($question_info['redirect']['target_id']);
		}
		
		if (is_numeric($_GET['rf']) and $_GET['rf'])
		{
			if ($from_question = $this->model('question')->get_question_info_by_id($_GET['rf']))
			{
				$redirect_message[] = AWS_APP::lang()->_t('从问题 %s 跳转而来', '<a href="' . get_js_url('/question/' . $_GET['rf'] . '?rf=false') . '">' . $from_question['question_content'] . '</a>');
			}
		}
		
		if ($question_info['redirect'] and ! $_GET['rf'])
		{
			if ($target_question)
			{
				HTTP::redirect('/question/' . $question_info['redirect']['target_id'] . '?rf=' . $question_info['question_id']);
			}
			else
			{
				$redirect_message[] = AWS_APP::lang()->_t('重定向目标问题已被删除, 将不再重定向问题');
			}
		}
		else if ($question_info['redirect'])
		{
			if ($target_question)
			{
				$message = AWS_APP::lang()->_t('此问题将跳转至') . ' <a href="' . get_js_url('/question/' . $question_info['redirect']['target_id'] . '?rf=' . $question_info['question_id']) . '">' . $target_question['question_content'] . '</a>';
				
				if ($this->user_id && ($this->user_info['permission']['is_administortar'] OR $this->user_info['permission']['is_moderator'] OR (!$this->question_info['lock'] AND $this->user_info['permission']['redirect_question'])))
				{
					$message .= '&nbsp; (<a href="javascript:;" onclick="ajax_request(G_BASE_URL + \'/question/ajax/redirect/\', \'item_id=' . $question_info['question_id'] . '\');">' . AWS_APP::lang()->_t('撤消重定向') . '</a>)';
				}
				
				$redirect_message[] = $message;
			}
			else
			{
				$redirect_message[] = AWS_APP::lang()->_t('重定向目标问题已被删除, 将不再重定向问题');
			}
		}
		
		if ($question_info['has_attach'])
		{
			$question_info['attachs'] = $this->model('publish')->get_attach('question', $question_info['question_id'], 'min');
			
			$question_info['attachs_ids'] = FORMAT::parse_attachs($question_info['question_detail'], true);
		}
		
		if ($question_info['category_id'] AND get_setting('category_enable') == 'Y')
		{
			$question_info['category_info'] = $this->model('system')->get_category_info($question_info['category_id']);
		}
		
		$question_info['user_info'] = $this->model('account')->get_user_info_by_uid($question_info['published_uid'], true);
		
		if ($_GET['column'] != 'log')
		{
			$this->model('question')->calc_popular_value($question_info['question_id']);
			$this->model('question')->update_views($question_info['question_id']);
			
			if (is_numeric($_GET['uid']))
			{
				$answer_list_where[] = 'uid = ' . intval($_GET['uid']);
				$answer_count_where = 'uid = ' . intval($_GET['uid']);
			}
			else if ($_GET['uid'] == 'focus' and $this->user_id)
			{
				if ($friends = $this->model('follow')->get_user_friends($this->user_id, false))
				{
					foreach ($friends as $key => $val)
					{
						$follow_uids[] = $val['uid'];
					}
				}
				else
				{
					$follow_uids[] = 0;
				}
				
				$answer_list_where[] = 'uid IN(' . implode($follow_uids, ',') . ')';
				$answer_count_where = 'uid IN(' . implode($follow_uids, ',') . ')';
				$answer_order_by = 'add_time ASC';
			}
			else if ($_GET['sort_key'] == 'add_time')
			{
				$answer_order_by = $_GET['sort_key'] . " " . $_GET['sort'];
			}
			else
			{
				$answer_order_by = "agree_count " . $_GET['sort'] . ", against_count ASC, add_time ASC";
			}
			
			if ($answer_count_where)
			{
				$answer_count = $this->model('answer')->get_answer_count_by_question_id($question_info['question_id'], $answer_count_where);
			}
			else
			{
				$answer_count = $question_info['answer_count'];
			}
			
			if (! $this->user_id)
			{
				if ($_GET['fromuid'])
				{
					HTTP::set_cookie('fromuid', $_GET['fromuid']);
				}
			}
			
			if (isset($_GET['answer_id']) and (! $this->user_id OR $_GET['single']))
			{
				$answer_list = $this->model('answer')->get_answer_list_by_question_id($question_info['question_id'], 1, 'answer_id = ' . intval($_GET['answer_id']));
			}
			else if (! $this->user_id && !$this->user_info['permission']['answer_show'])
			{
				if ($question_info['best_answer'])
				{
					$answer_list = $this->model('answer')->get_answer_list_by_question_id($question_info['question_id'], 1, 'answer_id = ' . intval($question_info['best_answer']));
				}
				else
				{
					$answer_list = $this->model('answer')->get_answer_list_by_question_id($question_info['question_id'], 1, null, 'agree_count DESC');
				}
			}
			else
			{
				if ($answer_list_where)
				{
					$answer_list_where = implode(' AND ', $answer_list_where);
				}
				
				$answer_list = $this->model('answer')->get_answer_list_by_question_id($question_info['question_id'], calc_page_limit($_GET['page'], 100), $answer_list_where, $answer_order_by);
			}
			
			// 最佳回复预留
			$answers[0] = '';

			if (! is_array($answer_list))
			{
				$answer_list = array();
			}
			
			$answer_ids = array();
			$answer_uids = array();
			
			foreach ($answer_list as $answer)
			{
				$answer_ids[] = $answer['answer_id'];
				$answer_uids[] = $answer['uid'];
				
				if ($answer['has_attach'])
				{
					$has_attach_answer_ids[] = $answer['answer_id'];
				}
			}
			
			if (!in_array($question_info['best_answer'], $answer_ids) AND intval($_GET['page']) < 2)
			{
				$answer_list = array_merge($this->model('answer')->get_answer_list_by_question_id($question_info['question_id'], 1, 'answer_id = ' . $question_info['best_answer']), $answer_list);
			}
			
			if ($answer_ids)
			{
				$answer_agree_users = $this->model('answer')->get_vote_user_by_answer_ids($answer_ids);
				
				$answer_vote_status = $this->model('answer')->get_answer_vote_status($answer_ids, $this->user_id);
				
				$answer_users_rated_thanks = $this->model('answer')->users_rated('thanks', $answer_ids, $this->user_id);
				$answer_users_rated_uninterested = $this->model('answer')->users_rated('uninterested', $answer_ids, $this->user_id);
				$answer_attachs = $this->model('publish')->get_attachs('answer', $has_attach_answer_ids, 'min');
			}
			
			foreach ($answer_list as $answer)
			{
				if ($answer['has_attach'])
				{
					$answer['attachs'] = $answer_attachs[$answer['answer_id']];
					
					$answer['insert_attach_ids'] = FORMAT::parse_attachs($answer['answer_content'], true);
				}
				
				$answer['user_rated_thanks'] = $answer_users_rated_thanks[$answer['answer_id']];
				$answer['user_rated_uninterested'] = $answer_users_rated_uninterested[$answer['answer_id']];
				
				$answer['answer_content'] = $this->model('question')->parse_at_user(FORMAT::parse_attachs(nl2br(FORMAT::parse_markdown($answer['answer_content']))));
				
				$answer['agree_users'] = $answer_agree_users[$answer['answer_id']];
				$answer['agree_status'] = $answer_vote_status[$answer['answer_id']];
				
				if ($question_info['best_answer'] == $answer['answer_id'] AND intval($_GET['page']) < 2)
				{
					$answers[0] = $answer;
				}
				else
				{
					$answers[] = $answer;
				}
			}
			
			if (! $answers[0])
			{
				unset($answers[0]);
			}
			
			if (get_setting('answer_unique') == 'Y')
			{
				if ($this->model('answer')->has_answer_by_uid($question_info['question_id'], $this->user_id))
				{
					TPL::assign('user_answered', TRUE);
				}
			}
			
			TPL::assign('answers', $answers);
			TPL::assign('answer_count', $answer_count);
		}
		
		if ($this->user_id)
		{
			TPL::assign('question_thanks', $this->model('question')->get_question_thanks($question_info['question_id'], $this->user_id));
			
			TPL::assign('invite_users', $this->model('question')->get_invite_users($question_info['question_id'], array($question_info['published_uid'])));
			
			TPL::assign('user_follow_check', $this->model('follow')->user_follow_check($this->user_id, $question_info['published_uid']));
			
			if ($this->user_info['draft_count'] > 0)
			{
				TPL::assign('draft_content', $this->model('draft')->get_data($question_info['question_id'], 'answer', $this->user_id));
			}
		}
		
		$question_info['question_detail'] = FORMAT::parse_attachs(nl2br(FORMAT::parse_markdown($question_info['question_detail'])));
		
		TPL::assign('question_info', $question_info);
		TPL::assign('question_focus', $this->model('question')->has_focus_question($question_info['question_id'], $this->user_id));
		
		$question_topics = $this->model('topic')->get_topics_by_item_id($question_info['question_id'], 'question');
		
		if (sizeof($question_topics) == 0 AND $this->user_id)
		{
			$related_topics = $this->model('question')->get_related_topics($question_info['question_content']);
			
			TPL::assign('related_topics', $related_topics);
		}
		
		TPL::assign('question_topics', $question_topics);
		
		$question_related_list = $this->model('question')->get_related_question_list($question_info['question_id'], $question_info['question_content']);
		
		TPL::assign('question_related_list', $question_related_list);
		
		if ($question_related_list)
		{
			foreach ($question_related_list AS $key => $val)
			{
				$question_related_ids[] = $val['question_id'];
			}
			
			$exclude_helpful_uids[] = $this->user_id;
			$exclude_helpful_uids[] = $question_info['published_uid'];
			
			if ($answer_users = $this->model('question')->get_answers_uid_by_question_id($question_info['question_id']))
			{
				foreach ($answer_users AS $key => $val)
				{
					$exclude_helpful_uids[] = $val['uid'];
				}
			}
			
			print_r($question_related_list); die;
			
			if ($question_related_ids)
			{
				TPL::assign('helpful_users', $this->model('question')->get_helpful_users($question_related_ids, get_setting('question_helpful_users_limit'), $exclude_helpful_uids));
			}
		}
		
		$this->crumb($question_info['question_content'], '/question/' . $question_info['question_id']);
		
		if ($_GET['column'] == 'log')
		{
			$this->crumb(AWS_APP::lang()->_t('日志'), '/question/id-' . $question_info['question_id'] . '__column-log');
		}
		else
		{
			TPL::assign('human_valid', human_valid('answer_valid_hour'));
			
			if ($this->user_id)
			{		
				TPL::assign('pagination', AWS_APP::pagination()->initialize(array(
					'base_url' => get_js_url('/question/id-' . $question_info['question_id'] . '__sort_key-' . $_GET['sort_key'] . '__sort-' . $_GET['sort'] . '__uid-' . $_GET['uid']), 
					'total_rows' => $answer_count,
					'per_page' => 100
				))->create_links());
			}
		}

		TPL::set_meta('keywords', implode(',', $this->model('system')->analysis_keyword($question_info['question_content'])));
		
		TPL::set_meta('description', $question_info['question_content'] . ' - ' . cjk_substr(str_replace("\r\n", ' ', strip_tags($question_info['question_detail'])), 0, 128, 'UTF-8', '...'));
		
		if (get_setting('advanced_editor_enable') == 'Y')
		{			
			// codemirror
			TPL::import_css('js/editor/codemirror/lib/codemirror.css');
			TPL::import_js('js/editor/codemirror/lib/codemirror.js');
			TPL::import_js('js/editor/codemirror/lib/util/continuelist.js');
			TPL::import_js('js/editor/codemirror/mode/xml/xml.js');
			TPL::import_js('js/editor/codemirror/mode/markdown/markdown.js');

			// editor
			TPL::import_js('js/editor/jquery.markitup.js');
			TPL::import_js('js/editor/markdown.js');
			TPL::import_js('js/editor/sets/default/set.js');
		}
		
		TPL::assign('attach_access_key', md5($this->user_id . time()));
		TPL::assign('redirect_message', $redirect_message);
		
		TPL::output('question/index');
	}

	function unverify_modify_action()
	{
		if (! $question_info = $this->model('question')->get_question_info_by_id($_GET['question_id']) or ! $_GET['log_id'])
		{
			H::redirect_msg(AWS_APP::lang()->_t('问题不存在'), '/');
		}
		
		if (($question_info['published_uid'] != $this->user_id) && (! $this->user_info['permission']['is_administortar']) && (! $this->user_info['permission']['is_moderator']))
		{
			H::redirect_msg(AWS_APP::lang()->_t('你没有权限进行此操作'), '/');
		}
		
		$this->model('question')->unverify_modify($_GET['question_id'], $_GET['log_id']);
		
		H::redirect_msg(AWS_APP::lang()->_t('取消确认修改成功, 正在返回...'), '/question/id-' . $_GET['question_id'] . '__column-log__rf-false');
	}

	function verify_modify_action()
	{
		if (! $question_info = $this->model('question')->get_question_info_by_id($_GET['question_id']) or ! $_GET['log_id'])
		{
			H::redirect_msg(AWS_APP::lang()->_t('问题不存在'), '/');
		}
		
		if (($question_info['published_uid'] != $this->user_id) && (! $this->user_info['permission']['is_administortar']) && (! $this->user_info['permission']['is_moderator']))
		{
			H::redirect_msg(AWS_APP::lang()->_t('你没有权限进行此操作'), '/');
		}
		
		$this->model('question')->verify_modify($_GET['question_id'], $_GET['log_id']);
		
		H::redirect_msg(AWS_APP::lang()->_t('确认修改成功, 正在返回...'), '/question/id-' . $_GET['question_id'] . '__column-log__rf-false');
	}
}