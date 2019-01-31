<?php
/**
 * First checkout step: user auth or gathering of essencial customer contact data.
 */
class shopCheckoutAuthStep extends shopCheckoutStep
{
    public function process($data, $prepare_result)
    {
        // Authorised contact we get from shopOrder
        /** @var waContact $contact */
        $contact = $data['order']['contact'];
        $contact_id = ifempty($contact, 'id', null);
        if ($contact_id) {
            try {
                $contact->getName();
            } catch (waException $e) {
                $contact_id = null;
                $contact = new waContact();
                $data['order']['contact_id'] = null;
            }
        }

        $data['contact'] = $contact;

        // Mode selected by user we get from POST (unless user is authorised)
        if ($contact_id) {
            $selected_mode = $contact['is_company'] ? shopCheckoutConfig::CUSTOMER_TYPE_COMPANY : shopCheckoutConfig::CUSTOMER_TYPE_PERSON;
        } else {
            $selected_mode = ifset($data, 'input', 'auth', 'mode', shopCheckoutConfig::CUSTOMER_TYPE_PERSON);
        }

        $errors = [];
        $config = $this->getCheckoutConfig();
        $auth_fields = $config->getAuthFields();

        // Determine which mode is selected - person or company
        if (1 == count($auth_fields)) {
            $selected_mode = key($auth_fields);
            if ($contact_id) {
                if ($contact['is_company'] && $selected_mode == shopCheckoutConfig::CUSTOMER_TYPE_PERSON) {
                    // Authorised as company but company mode is disabled in checkout. Cannot proceed.
                    $errors[] = [
                        'name'    => 'auth[mode]',
                        'text'    => _w('You are logged in as a company. Please log out and login again as a person.'),
                        'section' => $this->getId(),
                    ];
                } elseif (!$contact['is_company'] && $selected_mode == shopCheckoutConfig::CUSTOMER_TYPE_COMPANY) {
                    // Authorised as person but person mode is disabled in checkout. Cannot proceed.
                    $errors[] = [
                        'name'    => 'auth[mode]',
                        'text'    => _w('You are logged in as a person. Please log out and log in again as a company.'),
                        'section' => $this->getId(),
                    ];
                }
                if ($errors) {
                    return array(
                        'data'         => $data,
                        'result'       => $this->addRenderedHtml([
                            'contact_id'    => $contact_id,
                            'selected_mode' => null,
                            'fields_order'  => [],
                            'fields'        => null,
                        ], $data, $errors),
                        'errors'       => $errors,
                        'can_continue' => false,
                    );
                }
            }
        } elseif ($selected_mode && !isset($auth_fields[$selected_mode])) {
            $selected_mode = shopCheckoutConfig::CUSTOMER_TYPE_PERSON;
        }

        // If mode is selected, load contact data into it
        $form_fields = null;
        if ($selected_mode) {
            $fields = $auth_fields[$selected_mode];

            // Fetch base values from contact
            $base_values = [];
            foreach ($fields as $field_id => $field_info) {
                if ($field_info['type'] == 'Date') {
                    $base_value = $contact->get($field_id);
                } else {
                    $base_value = $contact->get($field_id, 'js');
                }
                if (!empty($field_info['multi'])) {
                    if (isset($base_value[0])) {
                        $base_value = $base_value[0];
                    }
                }
                if (is_array($base_value)) {
                    if ($field_info['type'] == 'Birthday') {
                        $year = ifset($base_value, 'data', 'year', null);
                        $month = ifset($base_value, 'data', 'month', null);
                        $day = ifset($base_value, 'data', 'day', null);
                        if ($year && $month && $day) {
                            if ($month < 10) {
                                $month = '0'.$month;
                            }
                            if ($day < 10) {
                                $day = '0'.$day;
                            }
                            $base_value = "{$year}-{$month}-{$day}";
                        } else {
                            $base_value = '';
                        }
                    } elseif (isset($base_value['data'])) {
                        $base_value = $base_value['data'];
                    } elseif (isset($base_value['value'])) {
                        $base_value = $base_value['value'];
                    }
                    if (!is_string($base_value)) {
                        $base_value = '';
                    }
                }
                $base_values[$field_id] = (string) $base_value;
            }

            // Customer-supplied values from POST
            $input_values = ifset($data, 'input', 'auth', 'data', []);

            // Check if user just logged in. If so, put missing data into fields
            // despite there being (empty) values in old input
            $user_id_from_input = ifset($data, 'input', 'auth', 'user_id', '');
            if ($contact_id && $user_id_from_input != $contact_id) {
                $input_values = array_filter($input_values);
            }

            $delayed_errors = [];
            $form_fields = $config->formatContactFields($fields, $input_values, $base_values);
            foreach ($form_fields as $field_id => $field_info) {
                if (!empty($field_info['required']) && empty($form_fields[$field_id]['value'])) {
                    $delayed_errors['auth[data]['.$field_id.']'] = _w('This field is required.');
                }
            }

            if (!$contact_id) {
                $contact['is_company'] = (int) ($selected_mode == shopCheckoutConfig::CUSTOMER_TYPE_COMPANY);
            }
        } else {
            $errors[] = [
                'name'    => 'auth[mode]',
                'text'    => _w('Please select customer type.'),
                'section' => $this->getId(),
            ];
        }

        if (!empty($delayed_errors)) {
            $data['auth']['delayed_errors'] = $delayed_errors;
        }

        $result = $this->addRenderedHtml([
            'contact_id'        => $contact_id,
            'selected_mode'     => $selected_mode,
            'fields_order'      => $form_fields ? array_keys($form_fields) : [],
            'fields'            => $form_fields,
            'service_agreement' => ifset($data, 'input', 'auth', 'service_agreement', 0),
        ], $data, $errors);

        return [
            'data'         => $data,
            'result'       => $result,
            'errors'       => $errors,
            'can_continue' => $selected_mode && !$errors,
        ];
    }

    public function getTemplatePath()
    {
        return 'auth.html';
    }
}
